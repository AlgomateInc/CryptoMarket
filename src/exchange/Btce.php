<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BtceStyleExchange;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\ILifecycleHandler;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderExecution;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\Transaction;
use CryptoMarket\Record\TransactionType;

use MongoDB\BSON\UTCDateTime;

class Btce extends BtceStyleExchange implements ILifecycleHandler
{
    private $supportedPairs = array(); //associative, pair->productId
    private $minOrderSizes = array(); //associative, pair->size
    private $quotePrecisions = array(); //associative, pair->precision
    private $minPrices = array(); //associative, pair->minPrice
    private $feeSchedule; //FeeSchedule

    protected function getAuthQueryUrl(){
        return 'https://btc-e.com/tapi/';
    }

    public function Name(){
        return "Btce";
    }

    function init()
    {
        $this->feeSchedule = new FeeSchedule();
        $marketsInfo = CurlHelper::query('https://btc-e.com/api/3/info');
        foreach($marketsInfo['pairs'] as $pair => $info){
            $pairName = mb_strtoupper(str_replace('_', '', $pair));
            $this->supportedPairs[$pairName] = $pair;
            $this->minOrderSizes[$pairName] = $info['min_amount'];
            $this->quotePrecisions[$pairName] = $info['decimal_places'];
            $this->minPrices[$pairName] = $info['min_price'];
            $this->feeSchedule->addPairFee($pairName, $info['fee'], $info['fee']);
        }
    }

    public function balances()
    {
        $btce_info = $this->assertSuccessResponse($this->authQuery("getInfo"));

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = $btce_info['funds'][mb_strtolower($curr)];
        }

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        return $this->feeSchedule->getFee($pair, $tradingRole, $volume);
    }

    public function currentFeeSchedule()
    {
        return $this->feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        return $this->feeSchedule->getFee($pair, $tradingRole);
    }

    public function supportedCurrencyPairs(){
        return array_keys($this->supportedPairs);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        if ($pairRate < $this->minPrices[$pair])
            throw new \UnexpectedValueException('Input rate too low, unacceptable by exchange');
        return $this->minOrderSizes[$pair];
    }

    public function quotePrecision($pair, $pairRate)
    {
        return $this->quotePrecisions[$pair];
    }

    private function getCurrencyPairName($pair)
    {
        if(!$this->supports($pair))
            throw new \UnexpectedValueException('Currency pair not supported');

        return mb_strtolower(CurrencyPair::Base($pair)) . '_' . mb_strtolower(CurrencyPair::Quote($pair));
    }

    public function depth($currencyPair)
    {
        $d = CurlHelper::query('https://btc-e.com/api/2/' . $this->getCurrencyPairName($currencyPair) . '/depth');

        return new OrderBook($d);
    }

    public function ticker($pair)
    {
        $btcePairName = $this->getCurrencyPairName($pair);

        $rawTick = CurlHelper::query("https://btc-e.com/api/2/$btcePairName/ticker");

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $rawTick['ticker']['sell'];
        $t->ask = $rawTick['ticker']['buy'];
        $t->last = $rawTick['ticker']['last'];
        $t->volume = $rawTick['ticker']['vol_cur'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        return array();
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->executeTrade($pair, $quantity, $price, 'buy');
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->executeTrade($pair, $quantity, $price, 'sell');
    }

    private function executeTrade($pair, $quantity, $price, $side)
    {
        $btcePairName = $this->getCurrencyPairName($pair);

        $btce_result = $this->authQuery("Trade", array("pair" => "$btcePairName", "type" => $side,
            "amount" => $quantity, "rate" => $price ));

        //add custom fields to success response since they are useful in other places
        //wish btce did this for us...
        if($this->isOrderAccepted($btce_result)){
            $btce_result['return']['price'] = $price;
            $btce_result['return']['timestamp'] = time();

            //if order_id was not assigned by btce, make our own, deterministically
            if($btce_result['return']['order_id'] == 0)
                $btce_result['return']['order_id'] = 'ZeroOrderId' . sha1(json_encode($btce_result));
        }

        return $btce_result;
    }

    public function activeOrders()
    {
        return $this->authQuery("ActiveOrders", array("pair" => "btc_usd"));
    }

    public function hasActiveOrders()
    {
        $ao = $this->activeOrders();

        if($ao['success'] == 0 && $ao['error'] == "no orders")
            return false;

        return true;
    }

    public function tradeHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery("TradeHistory", array('from' => "$numFetched")));
            sleep(1);

            foreach ($res as $tid => $od) {
                $td = new Trade();
                $td->tradeId = $tid;
                $td->orderId = $od['order_id'];
                $td->exchange = $this->Name();
                $td->currencyPair = $od['pair'];
                $td->orderType = ($od['type'] == 'sell')? OrderType::SELL : OrderType::BUY;
                $td->price = $od['rate'];
                $td->quantity = $od['amount'];
                $td->timestamp = $od['timestamp'];

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

    public function transactionHistory($desiredCount = INF)
    {
        $numFetched = 0;
        $ret = array();

        do
        {
            $res = $this->assertSuccessResponse($this->authQuery("TransHistory", array('from' => "$numFetched")));
            sleep(1);

            foreach($res as $btxid => $btx)
            {
                $numFetched += 1;
                if($numFetched >= $desiredCount)
                    break;

                if($btx['type'] != 1 && $btx['type'] != 2)
                    continue;

                $tx = new Transaction();
                $tx->exchange = ExchangeName::Btce;
                $tx->id = $btxid;
                $tx->type = ($btx['type'] == 1)? TransactionType::Credit: TransactionType::Debit;
                $tx->currency = $btx['currency'];
                $tx->amount = $btx['amount'];
                $tx->timestamp = $btx['timestamp'];

                $ret[] = $tx;
            }

            printf("Fetched $numFetched transaction records...\n");
        }
        while ($numFetched < $desiredCount && count($res) == 1000);

        return $ret;
    }

    public function transactions()
    {
        $transactionList = $this->assertSuccessResponse($this->authQuery("TransHistory", array('count'=>1000)));

        $ret = array();
        foreach($transactionList as $btxid => $btx)
        {
            if($btx['type'] != 1 && $btx['type'] != 2)
                continue;

            $tx = new Transaction();
            $tx->exchange = ExchangeName::Btce;
            $tx->id = $btxid;
            $tx->type = ($btx['type'] == 1)? TransactionType::Credit: TransactionType::Debit;
            $tx->currency = $btx['currency'];
            $tx->amount = $btx['amount'];
            $tx->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate($btx['timestamp']));

            $ret[] = $tx;
        }

        return $ret;
    }

    public function cancel($orderId)
    {
        return $this->assertSuccessResponse(
            $this->authQuery('CancelOrder', array('order_id' => $orderId))
        );
    }

    public function isOrderAccepted($orderResponse)
    {
        if($orderResponse['success'] == 1){
            return isset($orderResponse['return']) &&
                isset($orderResponse['return']['received']) &&
                isset($orderResponse['return']['order_id']);
        }

        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        if($orderResponse['return']['remains'] == 0 &&
            $orderResponse['return']['order_id'] == 0)
            return false;

        $ao = $this->activeOrders();
        $orderId = $orderResponse['return']['order_id'];
        return isset($ao['return'][$orderId]);
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['return']['order_id'];
    }

    public function getOrderExecutions($orderResponse)
    {
        $execList = array();

        if(!$this->isOrderAccepted($orderResponse))
            return $execList;

        $orderId = $orderResponse['return']['order_id'];

        if($orderResponse['return']['received'] !== 0) {
            //the order has executions on insert
            $oe = new OrderExecution();
            $oe->orderId = $orderId;
            $oe->txid = 'ExecOnInsertTx';
            $oe->price = $orderResponse['return']['price']; //our custom added field
            $oe->quantity = $orderResponse['return']['received'];
            $oe->timestamp = $orderResponse['return']['timestamp']; //our custom added field
            $execList[] = $oe;
        }

        $history = $this->tradeHistory(100);
        foreach($history as $td){
            if($td instanceof Trade)
                if($td->orderId == $orderId)
                {
                    $oe = new OrderExecution();
                    $oe->txid = $td->tradeId;
                    $oe->orderId = $orderId;
                    $oe->quantity = $td->quantity;
                    $oe->price = $td->price;
                    $oe->timestamp = $td->timestamp;

                    $execList[] = $oe;
                }
        }

        return $execList;
    }

}

