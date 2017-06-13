<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ILifecycleHandler;
use CryptoMarket\Exchange\IMarginExchange;
use CryptoMarket\Exchange\NonceFactory;

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

use MongoDB\BSON\UTCDateTime;

class Bitfinex extends BaseExchange implements IMarginExchange, ILifecycleHandler{

    private $key;
    private $secret;
    private $nonceFactory;

    protected $supportedPairs = array();
    protected $minOrderSizes = array(); //assoc array pair->minordersize
    protected $quotePrecisions = array(); //assoc array pair->precision
    protected $feeSchedule; // FeeSchedule structure

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();
    }

    function init()
    {
        $pairs = CurlHelper::query($this->getApiUrl() . 'symbols');
        foreach($pairs as $pair){
            try{
                CurrencyPair::Base($pair); //checks the format of the pair to make sure it is standard
                $this->supportedPairs[] = mb_strtoupper($pair);
            }catch(\Exception $e){}
        }

        $minOrderSizes = CurlHelper::query($this->getApiUrl() . 'symbols_details');
        foreach($minOrderSizes as $symbolDetail){
            $pairName = mb_strtoupper($symbolDetail['pair']);
            if($this->supports($pairName)) {
                $this->minOrderSizes[$pairName] = $symbolDetail['minimum_order_size'];
                $this->quotePrecisions[$pairName] = $symbolDetail['price_precision'];
            }
        }

        // From https://www.bitfinex.com/fees
        $genericFeeSchedule = new FeeScheduleList();
        $genericFeeSchedule->push(new FeeScheduleItem(0.0, 5.0e5, 0.2, 0.1));
        $genericFeeSchedule->push(new FeeScheduleItem(5.0e5, 1.0e6, 0.2, 0.08));
        $genericFeeSchedule->push(new FeeScheduleItem(1.0e6, 2.5e6, 0.2, 0.06));
        $genericFeeSchedule->push(new FeeScheduleItem(2.5e6, 5.0e6, 0.2, 0.04));
        $genericFeeSchedule->push(new FeeScheduleItem(5.0e6, 7.5e6, 0.2, 0.02));
        $genericFeeSchedule->push(new FeeScheduleItem(7.5e6, 1.0e7, 0.2, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(1.0e7, 1.5e7, 0.18, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(1.5e7, 2.0e7, 0.16, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(2.0e7, 2.5e7, 0.14, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(2.5e7, 3.0e7, 0.12, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(3.0e7, INF, 0.10, 0.0));
        $this->feeSchedule = new FeeSchedule();
        $this->feeSchedule->setFallbackFees($genericFeeSchedule);
    }

    public function Name()
    {
        return "Bitfinex";
    }

    public function balances()
    {
        $balance_info = $this->authQuery("balances");

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += $balItem['amount'];
        }

        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        return $this->feeSchedule->getFee($pair, $tradingRole, $volume);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        $account_infos = $this->authQuery("account_infos")[0];
        foreach ($account_infos['fees'] as $pair_fees) {
            $feeSchedule->addPairFee($pair_fees['pairs'], $pair_fees['taker_fees'], $pair_fees['maker_fees']);
        }
        $feeSchedule->setFallbackFee($account_infos['taker_fees'], $account_infos['maker_fees']);
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        $account_infos = $this->authQuery("account_infos")[0];
        $base = CurrencyPair::Base($pair);
        foreach ($account_infos['fees'] as $pair_fees) {
            if ($pair_fees['pairs'] == $base) {
                if ($tradingRole == TradingRole::Maker) {
                    return $pair_fees['maker_fees'];
                } else if ($tradingRole == TradingRole::Taker) {
                    return $pair_fees['taker_fees'];
                }
            }
        }
        // base currency not found, return fallback 
        if ($tradingRole == TradingRole::Maker) {
            return $account_infos['maker_fees'];
        } else if ($tradingRole == TradingRole::Taker) {
            return $account_infos['taker_fees'];
        }
    }

    public function ticker($pair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . 'pubticker' . '/' . $pair);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['last_price'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = CurlHelper::query($this->getApiUrl() . 'trades' . '/' . $pair . "?timestamp=$sinceDate");

        $ret = array();

        foreach($tradeList as $raw) {
            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['tid'];
            $t->price = (float) $raw['price'];
            $t->quantity = (float) $raw['amount'];
            $t->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate($raw['timestamp']));
            $t->orderType = mb_strtoupper($raw['type']);

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . 'book' . '/' . $currencyPair .
            '?limit_bids=150&limit_asks=150&group=1');

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price){
        return $this->submitOrder('buy','exchange limit', $pair, $quantity, $price);
    }

    public function sell($pair, $quantity, $price){
        return $this->submitOrder('sell','exchange limit', $pair, $quantity, $price);
    }

    public function long($pair, $quantity, $price){
        return $this->submitOrder('buy','limit', $pair, $quantity, $price);
    }

    public function short($pair, $quantity, $price){
        return $this->submitOrder('sell','limit', $pair, $quantity, $price);
    }

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $quotePrecision = $this->quotePrecision($pair, $price);
        $result = $this->authQuery('order/new',array(
            'symbol' => mb_strtolower($pair),
            'amount' => "$quantity",
            'price' => number_format($price, $quotePrecision, '.', ''),
            'exchange' => mb_strtolower($this->Name()),
            'side' => "$side",
            'type' => "$type"
        ));

        return $result;
    }

    public function cancel($orderId)
    {
        $res = $this->authQuery('order/cancel', array(
            'order_id' => $orderId
        ));

        return $res;
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['order_id']) && isset($orderResponse['id']);
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $os = $this->authQuery('order/status', array('order_id' => $orderResponse['order_id']));

        return $os['is_live'];
    }

    public function getOrderExecutions($orderResponse)
    {
        $trades = $this->tradeHistory(50);

        $orderTx = array();

        foreach($trades as $t){

            if($t['order_id'] == $orderResponse['order_id']){
                $exec = new OrderExecution();
                $exec->txid = $t['tid'];
                $exec->orderId = $t['order_id'];
                $exec->quantity = $t['amount'];
                $exec->price = $t['price'];
                $exec->timestamp = $t['timestamp'];

                $orderTx[] = $exec;
            }
        }

        return $orderTx;
    }

    public function tradeHistory($desiredCount)
    {
        $ret = array();

        //get the last trades for all supported pairs
        foreach($this->supportedCurrencyPairs() as $pair){
            $th = $this->authQuery('mytrades',
                array('limit_trades' => $desiredCount,
                    'symbol' => mb_strtolower($pair)));

            //make a note of the currency pair on each returned item
            //bitfinex does not return this information
            for($i = 0; $i < count($th); $i++){
                $th[$i]['pair'] = $pair;
            }

            //merge with the rest of the history
            $ret = array_merge($ret, $th);
        }

        //sort history descending by timestamp (latest trade first)
        usort($ret, function($a, $b){
            $aTime = $a['timestamp'];
            $bTime = $b['timestamp'];

            if($aTime == $bTime)
                return 0;
            return ($aTime > $bTime)? -1 : 1;
        });

        //cut down to desired size and return
        $ret = array_slice($ret, 0, $desiredCount);
        return $ret;
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSizes[$pair];
    }

    public function quotePrecision($pair, $pairRate)
    {
        // quotePrecisions only gives the total number of significant figures, 
        // not the actual precision, so pair rate is needed to calculate it
        $order_of_magnitude = intval(floor(log10($pairRate))) + 1;
        return $this->quotePrecisions[$pair] - $order_of_magnitude;
    }

    protected function authQuery($method, array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new \Exception('No way to get nonce!');

        $req['request'] = '/v1/'.$method;
        $req['nonce'] = strval($this->nonceFactory->get());

        $payload = base64_encode(json_encode($req));
        $sign = hash_hmac('sha384', $payload, $this->secret);

        // generate the extra headers
        $headers = $this->generateHeaders($this->key, $payload, $sign);

        return CurlHelper::query($this->getApiUrl() . $method, $payload, $headers);
    }

    protected function generateHeaders($key, $payload, $signature)
    {
        return array(
            'X-BFX-APIKEY: '.$key,
            'X-BFX-PAYLOAD: '.$payload,
            'X-BFX-SIGNATURE: '.$signature
        );
    }

    public function positions()
    {
        $rawPosList = $this->authQuery('positions');

        $retList = array();
        foreach($rawPosList as $p)
        {
            $pos = new Trade();
            $pos->currencyPair = mb_strtoupper($p['symbol']);
            $pos->exchange = Exchange::Bitfinex;
            $pos->orderType = ($p['amount'] < 0)? OrderType::SELL : OrderType::BUY;
            $pos->price = $p['base'];
            $pos->quantity = (string)abs($p['amount']);
            $pos->timestamp = $p['timestamp'];

            $retList[] = $pos;
        }

        return $retList;
    }

    function getApiUrl()
    {
        return 'https://api.bitfinex.com/v1/';
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['order_id'];
    }
}

