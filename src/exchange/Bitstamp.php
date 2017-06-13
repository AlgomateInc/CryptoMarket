<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\ILifecycleHandler;
use CryptoMarket\Exchange\NonceFactory;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\FeeScheduleItem;
use CryptoMarket\Record\FeeScheduleList;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderExecution;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;
use CryptoMarket\Record\TransactionType;

use MongoDB\BSON\UTCDateTime;

class Bitstamp extends BaseExchange implements ILifecycleHandler
{
    private $custid;
    private $key;
    private $secret;
    private $nonceFactory;

    private $supportedPairs = array();
    private $productIds = array();

    private $feeSchedule;

    public function __construct($custid, $key, $secret)
    {
        $this->custid = $custid;
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();

        $this->supportedPairs = array(CurrencyPair::BTCUSD,
            CurrencyPair::BTCEUR,
            CurrencyPair::XRPUSD,
            CurrencyPair::XRPEUR,
            CurrencyPair::XRPBTC);
        foreach($this->supportedPairs as $pair) {
            $this->productIds[$pair] = mb_strtolower($pair);
        }

        // from https://www.bitstamp.net/fee_schedule/
        $this->feeSchedule = new FeeSchedule();
        $btceurSchedule = new FeeScheduleList();
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(0.0, 1.8e4, 0.25));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(1.8e4, 9.0e4, 0.24));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(9.0e4, 1.8e5, 0.22));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(1.8e5, 3.6e5, 0.20));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(3.6e5, 5.4e5, 0.15));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(5.4e5, 9.0e5, 0.14));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(9.0e5, 1.8e6, 0.13));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(1.8e6, 3.6e6, 0.12));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(3.6e6, 1.8e7, 0.11));
        $btceurSchedule->push(FeeScheduleItem::newWithoutRole(1.8e7, INF, 0.10));
        $this->feeSchedule->addPairFees(CurrencyPair::BTCEUR, $btceurSchedule);
        $this->feeSchedule->addPairFees(CurrencyPair::XRPEUR, $btceurSchedule);

        $btcusdSchedule = new FeeScheduleList();
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(0.0, 2.0e4, 0.25));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(2.0e4, 1.0e5, 0.24));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(1.0e5, 2.0e5, 0.22));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(2.0e5, 4.0e5, 0.20));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(4.0e5, 6.0e5, 0.15));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(6.0e5, 1.0e6, 0.14));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(1.0e6, 2.0e6, 0.13));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(2.0e6, 4.0e6, 0.12));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(4.0e6, 2.0e7, 0.11));
        $btcusdSchedule->push(FeeScheduleItem::newWithoutRole(2.0e7, INF, 0.10));
        $this->feeSchedule->addPairFees(CurrencyPair::BTCUSD, $btcusdSchedule);
        $this->feeSchedule->addPairFees(CurrencyPair::XRPUSD, $btcusdSchedule);

        $this->feeSchedule->addPairFee(CurrencyPair::EURUSD, 0.2, 0.2);
        $this->feeSchedule->setFallbackFees($btcusdSchedule);
    }

    public function init()
    {
    }

    public function Name()
    {
        return 'Bitstamp';
    }

    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    public function quotePrecision($pair, $pairRate)
    {
        $base = CurrencyPair::Base($pair);
        if ($base === Currency::XRP) {
            return max(5, parent::quotePrecision($pair, $pairRate));
        } else {
            return parent::quotePrecision($pair, $pairRate);
        }
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        $basePrecision = $this->basePrecision($pair, $pairRate);
        $quotePrecision = $this->quotePrecision($pair, $pairRate);
        $stringRate = number_format($pairRate, $quotePrecision, '.', '');
        //minimum is 5 units of fiat currency, e.g. $5
        return bcdiv(5.0, $stringRate, $basePrecision) + bcpow(10, -1 * $basePrecision, $basePrecision);
    }

    public function balances()
    {
        $bstamp_info = $this->assertSuccessResponse($this->authQuery('balance'));

        $balances = array();
        foreach ($this->supportedCurrencies() as $curr) {
            $balances[$curr] = $bstamp_info[mb_strtolower($curr) . '_balance'];
        }

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        return $this->feeSchedule->getFee($pair, $tradingRole, $volume);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        $bstamp_info = $this->assertSuccessResponse($this->authQuery('balance/'));
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $bstamp_pair = mb_strtolower($pair);
            $fee = $bstamp_info[$bstamp_pair . '_fee'];
            $feeSchedule->addPairFee($pair, $fee, $fee);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        $bstamp_pair = mb_strtolower($pair);
        $bstamp_info = $this->assertSuccessResponse($this->authQuery('balance/'.$bstamp_pair));
        return $bstamp_info['fee'];
    }

    public function depth($currencyPair)
    {
        $this->assertValidCurrencyPair($currencyPair);

        $bstamp_depth = CurlHelper::query($this->getAPIUrl() . 'order_book/' . $this->productIds[$currencyPair]);

        $bstamp_depth['bids'] = array_slice($bstamp_depth['bids'],0,150);
        $bstamp_depth['asks'] = array_slice($bstamp_depth['asks'],0,150);

        return new OrderBook($bstamp_depth);
    }

    public function ticker($pair)
    {
        $this->assertValidCurrencyPair($pair);

        $raw = CurlHelper::query($this->getAPIUrl() . 'ticker/' . $this->productIds[$pair]);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['last'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        //TODO
        return array();
    }

    private function assertValidCurrencyPair($pair)
    {
        if (false == in_array($pair, $this->supportedCurrencyPairs())) {
            throw new \UnexpectedValueException("Currency pair not supported");
        }
    }

    public function buy($pair, $quantity, $price)
    {
        $this->assertValidCurrencyPair($pair);

        return $this->authQuery('buy/' . $this->productIds[$pair], 
            array("amount" => $quantity, "price" => $price));
    }

    public function sell($pair, $quantity, $price)
    {
        $this->assertValidCurrencyPair($pair);

        return $this->authQuery('sell/' . $this->productIds[$pair], 
            array("amount" => $quantity, "price" => $price));
    }

    public function cancel($orderId)
    {
        $response = $this->authQuery('cancel_order', array('id' => $orderId));
        return $response['id'] == $orderId;
    }

    public function activeOrders()
    {
        $orders = array();
        foreach ($this->productIds as $pair=>$productId) {
            $orders = array_merge($orders, $this->authQuery('open_orders/' . $productId));
        }
        return $orders;
    }

    public function hasActiveOrders()
    {
        $ao = $this->activeOrders();

        return count($ao) > 0;
    }

    public function isOrderAccepted($orderResponse)
    {
        if(!isset($orderResponse['error'])){
            return isset($orderResponse['id']) && isset($orderResponse['amount']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $orderId = $orderResponse['id'];
        $ao = $this->activeOrders();

        //search the active order list for our order
        for ($i = 0;$i < count($ao);$i++)
        {
            $order = $ao[$i];

            if ($order['id'] == $orderId)
                return true;
        }

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $usrTx = $this->authQuery('user_transactions');

        $orderTx = array();

        for ($i = 0; $i< count($usrTx); $i++)
        {
            if ($usrTx[$i]['order_id'] == $orderResponse['id'])
            {
                $exec = new OrderExecution();
                $exec->txid = $usrTx[$i]['id'];
                $exec->orderId = $usrTx[$i]['order_id'];

                if (isset($usrTx[$i]['btc']) && $usrTx[$i]['btc'] != 0) {
                    $exec->quantity = abs(floatval($usrTx[$i]['btc']));
                } else if (isset($usrTx[$i]['xrp']) && $usrTx[$i]['xrp'] != 0) {
                    $exec->quantity = abs(floatval($usrTx[$i]['xrp']));
                }

                if (isset($usrTx[$i]['btc_usd']) && $usrTx[$i]['btc_usd'] != 0) {
                    $exec->price = abs($usrTx[$i]['btc_usd']);
                } else if (isset($usrTx[$i]['btc_eur']) && $usrTx[$i]['btc_eur'] != 0){
                    $exec->price = abs($usrTx[$i]['btc_eur']);
                } else if (isset($usrTx[$i]['xrp_usd']) && $usrTx[$i]['xrp_usd'] != 0){
                    $exec->price = abs($usrTx[$i]['xrp_usd']);
                } else if (isset($usrTx[$i]['xrp_eur']) && $usrTx[$i]['xrp_eur'] != 0){
                    $exec->price = abs($usrTx[$i]['xrp_eur']);
                }

                $exec->timestamp = strtotime($usrTx[$i]['datetime']);

                $orderTx[] = $exec;
            }
        }

        return $orderTx;
    }

    private function assertSuccessResponse($response)
    {
        if(isset($response['error']))
            throw new \Exception($response['error']);

        return $response;
    }

    public function transactions()
    {
        $response =  $this->authQuery('user_transactions', array('limit'=>1000));
        $this->assertSuccessResponse($response);

        $ret = array();
        foreach($response as $btx)
        {
            //skip over trades
            if($btx['type'] == 2)
                continue;

            $tx = new Transaction();
            $tx->exchange = ExchangeName::Bitstamp;
            $tx->id = $btx['id'];
            $tx->type = ($btx['type'] == 0)? TransactionType::Credit : TransactionType::Debit;
            foreach ($this->supportedCurrencies() as $curr) {
                $amount = floatval($btx[mb_strtolower($curr)]);
                if ($amount != 0) {
                    $tx->currency = $curr;
                    $tx->amount = $amount;
                }
            }
            $tx->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate(strtotime($btx['datetime'])));

            $ret[] = $tx;
        }

        return $ret;
    }

    public function tradeHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery('user_transactions',
                array('limit'=>1000, 'offset'=>$numFetched)));
            sleep(1);

            foreach ($res as $od) {
                if($od['type'] != 2) // not including transactions
                    continue;

                $td = new Trade();
                $td->exchange = $this->Name();
                $td->orderId = $od['order_id'];
                $td->tradeId = $od['id'];
                foreach ($this->supportedCurrencyPairs() as $pair) {
                    $base = mb_strtolower(CurrencyPair::Base($pair));
                    $quote = mb_strtolower(CurrencyPair::Quote($pair));
                    if ($od[$quote] != 0 && $od[$base] != 0) {
                        $td->currencyPair = $pair;
                        $td->orderType = ($od[$quote] > 0)? OrderType::SELL : OrderType::BUY;
                        $td->price = $od[$base . '_' . $quote];
                        $td->quantity = abs($od[$base]);
                    }
                }
                $td->timestamp = $od['datetime'];

                $ret[] = $td;
                $numFetched += 1;

                if($numFetched >= $desiredCount)
                    break;
            }

            printf("Fetched $numFetched trade records...\n");
        }
        while ($numFetched < $desiredCount && count($res) == 1000);

        return $ret;
    }

    private function getAPIUrl()
    {
        return 'https://www.bitstamp.net/api/v2/';
    }

    private function authQuery($method, array $req = array()) 
    {
        if (!$this->nonceFactory instanceof NonceFactory)
            throw new \Exception('No way to get nonce!');

        // generate the POST data string
        $req['key'] = $this->key;
        $req['nonce'] = $this->nonceFactory->get();
        $req['signature'] = mb_strtoupper(hash_hmac("sha256", $req['nonce'] . $this->custid . $this->key, $this->secret));
        $post_data = http_build_query($req, '', '&');

        return CurlHelper::query($this->getAPIUrl() . $method . '/', $post_data);
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }
}

