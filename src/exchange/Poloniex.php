<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 7/21/2015
 * Time: 7:46 PM
 */

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\DateHelper;
use CryptoMarket\Helper\NonceFactory;

use CryptoMarket\Exchange\BaseExchange;

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

class Poloniex extends BaseExchange implements ILifecycleHandler
{
    protected $trading_url = "https://poloniex.com/tradingApi";
    protected $public_url = "https://poloniex.com/public";

    private $key;
    private $secret;
    private $nonceFactory;

    private $feeSchedule;
    private $supportedPairs;

    private $lastCall;
    const THROTTLE = 200000; // 5 calls / second

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

        $this->supportedPairs = array();
        $this->lastCall = DateHelper::totalMicrotime();
    }

    public function init()
    {
        $tickers = $this->publicQuery($this->public_url.'?command=returnTicker');
        foreach ($tickers as $pairName => $value) {
            $this->supportedPairs[] = $this->getStandardPairName($pairName);
        }
    }

    public function Name()
    {
        return 'Poloniex';
    }

    public function balances()
    {
        $bal = $this->privateQuery(array('command' => 'returnBalances'));

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
        $feeInfo = $this->privateQuery(array('command' => 'returnFeeInfo'));
        $takerFee = 0.0;
        $makerFee = 0.0;
        if (array_key_exists('takerFee', $feeInfo)) {
            $takerFee = bcmul($feeInfo['takerFee'], '100', 4);
        }
        if (array_key_exists('makerFee', $feeInfo)) {
            $makerFee = bcmul($feeInfo['makerFee'], '100', 4);
        }
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $feeSchedule->addPairFee($pair, $takerFee, $makerFee);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        $fee = 0.0;
        $feeInfo = $this->privateQuery(array('command' => 'returnFeeInfo'));
        if ($tradingRole == TradingRole::Maker) {
            $fee = floatval($feeInfo['makerFee']) * 100.0;
        } else if ($tradingRole == TradingRole::Taker) {
            $fee = floatval($feeInfo['takerFee']) * 100.0;
        }
        return $fee;
    }

    private function makeTransaction($ledgerItem, $transType)
    {
        $tx = new Transaction();
        $tx->exchange = $this->Name();
        $tx->type = $transType;
        $tx->id = $ledgerItem['txid'];
        $tx->currency = $ledgerItem['currency'];
        $tx->amount = floatval($ledgerItem['amount']);
        $tx->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($ledgerItem['timestamp']));
        return $tx;
    }

    public function transactions()
    {
        $ret = array();
        $ledger = $this->privateQuery(array('command' => 'returnDepositsWithdrawals',
            'start' => 0,
            'end' => time()));
        foreach ($ledger['deposits'] as $item) {
            if ($item['status'] == 'COMPLETE') {
                $ret[] = $this->makeTransaction($item, TransactionType::Credit);
            }
        }
        foreach ($ledger['withdrawals'] as $item) {
            if ($item['status'] == 'COMPLETE') {
                $ret[] = $this->makeTransaction($item, TransactionType::Debit);
            }
        }
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

    public function tickers()
    {
        $ret = array();
        
        $prices = $this->publicQuery($this->public_url.'?command=returnTicker');
        foreach ($prices as $pair => $price) {
            $t = new Ticker();
            $t->currencyPair = $this->getStandardPairName($pair);
            $t->bid = $price['highestBid'];
            $t->ask = $price['lowestAsk'];
            $t->last = $price['last'];
            $t->volume = $price['quoteVolume'];
            $ret[] = $t;
        }

        return $ret;
    }

    public function ticker($pair)
    {
        $mktPairName = $this->getPoloniexPairName($pair);
        $prices = $this->publicQuery($this->public_url.'?command=returnTicker');

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
        $mktPairName = $this->getPoloniexPairName($pair);

        $trades = $this->publicQuery($this->public_url.'?command=returnTradeHistory&currencyPair='. $mktPairName .
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
            $t->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($dt->getTimestamp()));

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $mktPairName = $this->getPoloniexPairName($currencyPair);
        $rawBook = $this->publicQuery($this->public_url.'?command=returnOrderBook&currencyPair='. $mktPairName);
        return new OrderBook($rawBook);
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->privateQuery(
            array(
                'command' => 'buy',
                'currencyPair' => mb_strtoupper($this->getPoloniexPairName($pair)),
                'rate' => $price,
                'amount' => $quantity
            )
        );
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->privateQuery(
            array(
                'command' => 'sell',
                'currencyPair' => mb_strtoupper($this->getPoloniexPairName($pair)),
                'rate' => $price,
                'amount' => $quantity
            )
        );
    }

    public function activeOrders()
    {
        return $this->privateQuery(
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
        return $this->privateQuery(
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
            $th = $this->privateQuery(
                array(
                    'command' => 'returnTradeHistory',
                    'currencyPair' => mb_strtoupper($this->getPoloniexPairName($pair))
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

    private function publicQuery($uri)
    {
        $this->lastCall = $this->throttleQuery($this->lastCall, self::THROTTLE);
        return CurlHelper::query($uri);
    }

    private function privateQuery(array $req = array()) {
        if (!$this->nonceFactory instanceof NonceFactory)
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

        $this->lastCall = $this->throttleQuery($this->lastCall, self::THROTTLE);
        return CurlHelper::query($this->trading_url, $post_data, $headers);
    }

    private function getStandardPairName($poloniexPair)
    {
        // Format is QUOTE_BASE, and no USD, only USDT
        $parts = explode('_', $poloniexPair);
        $quote = $parts[0];
        $base = $parts[1];
        if ($quote == Currency::USDT) {
            $quote = Currency::USD;
        }
        if ($base == Currency::USDT) {
            $base = Currency::USD;
        }
        return CurrencyPair::MakePair($base, $quote);
    }

    private function getPoloniexPairName($pair)
    {
        if (!$this->supports($pair)) {
            throw new \UnexpectedValueException('Currency pair not supported');
        }

        $base = CurrencyPair::Base($pair);
        $quote = CurrencyPair::Quote($pair);

        // No USD, only USDT
        if ($quote == Currency::USD) {
            $quote = Currency::USDT;
        }
        if ($base == Currency::USD) {
            $base = Currency::USDT;
        }

        // Poloniex reverses base and quote currency, so BTCUSD becomes USDT_BTC
        // There are 4 main quote currencies, BTC, ETH, XMR, and USDT

        return $quote . '_' . $base;
    }
}

