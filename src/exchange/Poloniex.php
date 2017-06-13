<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 7/21/2015
 * Time: 7:46 PM
 */

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BaseExchange;
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

use MongoDB\BSON\UTCDateTime;

class Poloniex extends BaseExchange
{
    protected $trading_url = "https://poloniex.com/tradingApi";
    protected $public_url = "https://poloniex.com/public";

    private $key;
    private $secret;
    private $nonceFactory;

    private $feeSchedule;

    public function __construct($key, $secret){
        $this->key = $key;
        $this->secret = $secret;

        $this->nonceFactory = new NonceFactory();

        // From https://poloniex.com/fees/
        $this->feeSchedule = new FeeSchedule();
        $fallbackSchedule = new FeeScheduleList();
        $fallbackSchedule->push(new FeeScheduleItem(0.0, 6.0e2, 0.25, 0.15));
        $fallbackSchedule->push(new FeeScheduleItem(6.0e2, 1.2e3, 0.24, 0.14));
        $fallbackSchedule->push(new FeeScheduleItem(1.2e3, 2.4e3, 0.22, 0.12));
        $fallbackSchedule->push(new FeeScheduleItem(2.4e3, 6.0e3, 0.20, 0.10));
        $fallbackSchedule->push(new FeeScheduleItem(6.0e3, 1.2e4, 0.16, 0.08));
        $fallbackSchedule->push(new FeeScheduleItem(1.2e4, 1.8e4, 0.14, 0.05));
        $fallbackSchedule->push(new FeeScheduleItem(1.8e4, 2.4e4, 0.12, 0.02));
        $fallbackSchedule->push(new FeeScheduleItem(2.4e4, 6.0e4, 0.10, 0.00));
        $fallbackSchedule->push(new FeeScheduleItem(6.0e4, 1.2e5, 0.08, 0.00));
        $fallbackSchedule->push(new FeeScheduleItem(1.2e5, INF, 0.05, 0.00));
        $this->feeSchedule->setFallbackFees($fallbackSchedule);
    }

    public function Name()
    {
        return 'Poloniex';
    }

    public function balances()
    {
        $bal = $this->query(
            array(
                'command' => 'returnBalances'
            )
        );

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $mktCurrName = mb_strtoupper($curr == Currency::USD? 'USDT' : $curr);
            if(isset($bal[$mktCurrName]))
                $balances[$curr] = $bal[$mktCurrName];
        }

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        // NOTE: all volumes are supposed to be in terms of BTC, so adjust 
        // volume in terms of BTC if the quote currency is not BTC
        $quote = CurrencyPair::Quote($pair);
        if ($quote != Currency::BTC) {
            $subPair = CurrencyPair::MakePair(Currency::BTC, $quote);
            $ticker = $this->ticker($subPair);
            $volume = $volume / $ticker->last;
        } 

        return $this->feeSchedule->getFee($pair, $tradingRole, $volume);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        $feeInfo = $this->query(array('command' => 'returnFeeInfo'));
        $takerFee = bcmul($feeInfo['takerFee'], '100', 4);
        $makerFee = bcmul($feeInfo['makerFee'], '100', 4);
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $feeSchedule->addPairFee($pair, $takerFee, $makerFee);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        $feeInfo = $this->query(array('command' => 'returnFeeInfo'));
        if ($tradingRole == TradingRole::Maker) {
            return floatval($feeInfo['makerFee']) * 100.0;
        } else if ($tradingRole == TradingRole::Taker) {
            return floatval($feeInfo['takerFee']) * 100.0;
        }
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs()
    {
        return array(CurrencyPair::LTCBTC, CurrencyPair::XMRBTC, CurrencyPair::BTCUSD,
            CurrencyPair::XCPBTC, CurrencyPair::MAIDBTC, CurrencyPair::ETHBTC,
            CurrencyPair::ETHUSD, 'ETCBTC', 'STEEM/BTC', 'XRPBTC');
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @param $pairRate Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        // total of $pairRate * orderSize must be at least 0.0001
        // otherwise minimum "amount" is 0.000001
        $MIN_TOTAL = 0.0001;
        $MIN_AMOUNT = 0.000001;

        $basePrecision = $this->basePrecision($pair, $pairRate);
        $minIncrement = bcpow(10, -1 * $basePrecision, $basePrecision);
        $stringRate = number_format($pairRate, $basePrecision, '.', '');
        $minOrder = bcdiv($MIN_TOTAL, $stringRate, $basePrecision) + $minIncrement;

        return max($minOrder, $MIN_AMOUNT);
    }

    public function ticker($pair)
    {
        $mktPairName = $this->getCurrencyPairName($pair);
        $prices = CurlHelper::query($this->public_url.'?command=returnTicker');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $prices[$mktPairName]['highestBid'];
        $t->ask = $prices[$mktPairName]['lowestAsk'];
        $t->last = $prices[$mktPairName]['last'];
        $t->volume = $prices[$mktPairName]['quoteVolume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $mktPairName = $this->getCurrencyPairName($pair);

        $trades = CurlHelper::query($this->public_url.'?command=returnTradeHistory&currencyPair='. $mktPairName .
            '&start=' . $sinceDate . '&end=' . time());

        $ret = array();

        foreach($trades as $raw) {
            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = md5($raw['date'] . $raw['type'] . $raw['rate'] . $raw['amount']);
            $t->price = (float) $raw['rate'];
            $t->quantity = (float) $raw['amount'];
            $t->orderType = mb_strtoupper($raw['type']);

            $dt = new \DateTime($raw['date']);
            $t->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate($dt->getTimestamp()));

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $mktPairName = $this->getCurrencyPairName($currencyPair);
        $rawBook = CurlHelper::query($this->public_url.'?command=returnOrderBook&currencyPair='. $mktPairName);
        return new OrderBook($rawBook);
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->query(
            array(
                'command' => 'buy',
                'currencyPair' => mb_strtoupper($this->getCurrencyPairName($pair)),
                'rate' => $price,
                'amount' => $quantity
            )
        );
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->query(
            array(
                'command' => 'sell',
                'currencyPair' => mb_strtoupper($this->getCurrencyPairName($pair)),
                'rate' => $price,
                'amount' => $quantity
            )
        );
    }

    public function activeOrders()
    {
        return $this->query(
            array(
                'command' => 'returnOpenOrders',
                'currencyPair' => 'all'
            )
        );
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
        return $this->query(
            array(
                'command' => 'cancelOrder',
                'orderNumber' => $orderId
            )
        );
    }

    public function isOrderAccepted($orderResponse)
    {
        if(isset($orderResponse['error']))
            return false;

        return true;
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        $ao = $this->activeOrders();

        foreach($ao as $pairOrders)
        {
            if(count($pairOrders) > 0)
            {
                foreach($pairOrders as $orderStatus)
                {
                    if($orderStatus['orderNumber'] == $this->getOrderID($orderResponse))
                        return true;
                }
            }
        }

        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        $trades = $this->tradeHistory(500);

        $orderTx = array();

        foreach($trades as $t){

            if($t['orderNumber'] == $this->getOrderID($orderResponse)){
                $exec = new OrderExecution();
                $exec->txid = $t['tradeID'];
                $exec->orderId = $t['orderNumber'];
                $exec->quantity = $t['amount'];
                $exec->price = $t['rate'];
                $exec->timestamp = $t['date'];

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
            $th = $this->query(
                array(
                    'command' => 'returnTradeHistory',
                    'currencyPair' => mb_strtoupper($this->getCurrencyPairName($pair))
                )
            );

            //make a note of the currency pair on each returned item
            for($i = 0; $i < count($th); $i++){
                $th[$i]['pair'] = $pair;
            }

            //merge with the rest of the history
            $ret = array_merge($ret, $th);
        }

        //sort history descending by timestamp (latest trade first)
        usort($ret, function($a, $b){
            $aTime = strtotime($a['date']);
            $bTime = strtotime($b['date']);

            if($aTime == $bTime)
                return 0;
            return ($aTime > $bTime)? -1 : 1;
        });

        //cut down to desired size and return
        $ret = array_slice($ret, 0, $desiredCount);
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['orderNumber'];
    }

    private function query(array $req = array()) {
        if(!$this->nonceFactory instanceof NonceFactory)
            throw new \Exception('No way to get nonce!');

        $req['nonce'] = $this->nonceFactory->get();

        // generate the POST data string
        $post_data = http_build_query($req, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secret);

        // generate the extra headers
        $headers = array(
            'Key: '.$this->key,
            'Sign: '.$sign,
        );

        return CurlHelper::query($this->trading_url, $post_data, $headers);
    }

    private function getCurrencyPairName($pair)
    {
        if(!$this->supports($pair))
            throw new \UnexpectedValueException('Currency pair not supported');

        $base = CurrencyPair::Base($pair);
        $quote = CurrencyPair::Quote($pair);

        //return USD market as tether
        if($pair == CurrencyPair::BTCUSD)
            return 'USDT_BTC';

        if($pair == CurrencyPair::ETHUSD)
            return 'USDT_ETH';

        //make the pair in the wacky poloniex way
        if($base == Currency::BTC)
            return $base . '_' . $quote;

        if($quote == Currency::BTC)
            return $quote . '_' . $base;

        throw new \Exception('Unsupported pair');
    }
}

