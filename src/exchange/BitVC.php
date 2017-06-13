<?php

/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 12:12 PM
 */

namespace CryptoMarket\Exchange;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\IMarginExchange;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;

class BitVC extends BaseExchange implements IMarginExchange{

    private $key;
    private $secret;

    function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    public function Name()
    {
        return 'BitVC';
    }

    protected function authQuery($method, array $req = array()) {

        $req['accessKey'] = $this->key;
        $req['coinType'] = 1;
        $req['created'] = time();

        $sigData = $req;
        $sigData['secretKey'] = $this->secret;
        $req['sign'] =  md5(http_build_query($sigData));

        return CurlHelper::query($this->getFuturesApiUrl() . $method, http_build_query($req));
    }

    function getFuturesApiUrl()
    {
        return 'https://api.bitvc.com/futures/';
    }

    function getFuturesMarketApiUrl()
    {
        return 'http://market.bitvc.com/futures/ticker_btc_week.js';
    }

    public function getOrderID($orderResponse)
    {
        // TODO: Implement getOrderID() method.
    }

    public function balances()
    {
        $bi = $this->authQuery('balance');

        $balances = array();
        $balances[Currency::BTC] = $bi['staticRights'];

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        // TODO
    }

    public function currentFeeSchedule()
    {
        // TODO
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        // TODO
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
        return array(CurrencyPair::BTCCNY);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @return mixed The minimum order size
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return 100.0/$pairRate; //min size for futures order is 100 yuan
    }

    public function ticker($pair)
    {
        if($pair != CurrencyPair::BTCCNY)
            throw new \InvalidArgumentException("Bad currency pair specified for market: $pair");

        $raw = CurlHelper::query($this->getFuturesMarketApiUrl());

        $t = new Ticker();
        $t->currencyPair = CurrencyPair::BTCCNY;
        $t->bid = $raw['buy'];
        $t->ask = $raw['sell'];
        $t->last = $raw['last'];
        $t->volume = $raw['vol'];

        return $t;
    }

    public function depth($currencyPair)
    {
        if($currencyPair != CurrencyPair::BTCCNY)
            throw new \InvalidArgumentException("Bad currency pair specified for market: $currencyPair");

        $raw = CurlHelper::query('http://market.bitvc.com/futures/depths_btc_week.js');

        $book = new OrderBook($raw);

        return $book;
    }

    public function long($pair, $quantity, $price)
    {
        // TODO: Implement long() method.
    }

    public function short($pair, $quantity, $price)
    {
        // TODO: Implement short() method.
    }

    public function positions()
    {
        $rawPosList = $this->authQuery('holdOrder/list');

        $retList = array();
        foreach($rawPosList as $contract)
        {
            foreach($contract as $p) {
                $pos = new Trade();
                $pos->currencyPair = CurrencyPair::BTCCNY;
                $pos->exchange = ExchangeName::BitVC;
                $pos->orderType = ($p['tradeType'] == 2) ? OrderType::SELL : OrderType::BUY;
                $pos->price = (string) $p['price'];
                $pos->quantity = (string) ($p['closeMoney'] / $p['price']);
                $pos->timestamp = $p['id'];

                $retList[] = $pos;
            }
        }

        return $retList;
    }

    public function buy($pair, $quantity, $price)
    {
        // TODO: Implement buy() method.
    }

    public function sell($pair, $quantity, $price)
    {
        // TODO: Implement sell() method.
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
        // TODO: Implement cancel() method.
    }

    public function isOrderAccepted($orderResponse)
    {
        // TODO: Implement isOrderAccepted() method.
    }

    public function isOrderOpen($orderResponse)
    {
        // TODO: Implement isOrderOpen() method.
    }

    public function getOrderExecutions($orderResponse)
    {
        // TODO: Implement getOrderExecutions() method.
    }

    public function tradeHistory($desiredCount)
    {
        // TODO: Implement tradeHistory() method.
    }

    public function trades($pair, $sinceDate)
    {
        return array();
    }
}

