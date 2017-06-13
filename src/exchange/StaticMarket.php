<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 10:12 PM
 */

namespace CryptoMarket\Exchange;

class StaticMarket extends BaseExchange
{
    private $validOrderIdList = array();

    public function Name()
    {
        return 'StaticMarket';
    }

    public function balances()
    {
        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 100000;
        }

        return $balances;
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
        return array(CurrencyPair::BTCUSD);
    }

    /**
     * @param $pair The pair we want to get minimum order size for
     * @param $pairRate Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate)
    {
        return 0.00000001;
    }

    public function ticker($pair)
    {
        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = 15;
        $t->ask = 16;
        $t->last = 15.5;
        $t->volume = 1200;

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $ret = array();

        $t = new Trade();
        $t->currencyPair = $pair;
        $t->exchange = $this->Name();
        $t->tradeId = '234923';
        $t->price = 15.5;
        $t->quantity = 28;
        $t->timestamp = new MongoDB\BSON\UTCDateTime();
        $t->orderType = OrderType::SELL;

        $ret[] = $t;

        return $ret;
    }

    public function depth($currencyPair)
    {
        $ob = new OrderBook();

        for($i = 0; $i < 5; $i++)
        {
            $b = new DepthItem();
            $b->price = 15 - $i;
            $b->quantity = $i * 10 + 10;
            $ob->bids[] = $b;

            $a = new DepthItem();
            $a->price = 16 + $i;
            $a->quantity = $i * 10 + 10;
            $ob->asks[] = $a;
        }

        return $ob;
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->createOrderResponse($pair, $quantity, $price, OrderType::BUY);
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->createOrderResponse($pair, $quantity, $price, OrderType::SELL);
    }

    private function createOrderResponse($pair, $quantity, $price, $side)
    {
        if(!$this->supports($pair) || $quantity <= 0 || $price <= 0)
            return array(
                'error' => true
            );

        $oid = uniqid($this->Name(),true);
        $this->validOrderIdList[] = $oid;

        return array(
            'orderId' => $oid,
            'pair' => $pair,
            'quantity' => $quantity,
            'price' => $price,
            'side' => $side
        );
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
        return array(
            'orderId' => $orderId,
            'cancelled' => true
        );
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['orderId']);
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

    public function getOrderID($orderResponse)
    {
        return $orderResponse['orderId'];
    }

}

