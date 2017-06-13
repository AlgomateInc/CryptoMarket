<?php

namespace CryptoMarket\Record;

use CryptoMarket\Record\DepthItem;
use CryptoMarket\Record\OrderType;

class OrderBook
{
    public $bids = array();
    public $asks = array();

    public function __construct($rawBook = null)
    {
        if ($rawBook === null)
            return;

        $bookSides = array(
            array($rawBook['bids'], & $this->bids),
            array($rawBook['asks'], & $this->asks));

        foreach ($bookSides as $bookSideItem) {
            foreach ($bookSideItem[0] as $item) {
                $b = new DepthItem();

                if(isset($item['price']))
                    $b->price = $item['price'];
                else
                    $b->price = $item[0];

                if(isset($item['amount']))
                    $b->quantity = $item['amount'];
                else
                    $b->quantity = $item[1];

                if(isset($item['timestamp']))
                    $b->timestamp = $item['timestamp'];

                $bookSideItem[1][] = $b;
            }
        }
    }

    public function volumeToPrice($px){
        $volume = 0;

        foreach($this->bids as $item){
            if(!($item instanceof DepthItem))
                break;
            if($px > $item->price)
                break;
            $volume += $item->quantity;
        }

        foreach($this->asks as $item){
            if(!($item instanceof DepthItem))
                break;
            if($px < $item->price)
                break;
            $volume += $item->quantity;
        }

        return $volume;
    }

    public function getOrderBookVolume($pricePercentage)
    {
        $bid = OrderBook::getInsideBookPrice($this, OrderType::BUY);
        $ask = OrderBook::getInsideBookPrice($this, OrderType::SELL);

        if ($bid === null || $ask === null)
            return null;

        $midpoint = ($bid + $ask)/2.0;

        $bidVolume = $this->volumeToPrice($midpoint * (1 - $pricePercentage / 100.0));
        $askVolume = $this->volumeToPrice($midpoint * (1 + $pricePercentage / 100.0));

        if($bidVolume == 0 || $askVolume == 0)
            return null;

        return array('bid' => $bid, 'bidVolume' => $bidVolume, 'ask' => $ask, 'askVolume' => $askVolume);
    }

    public static function getInsideBookPrice(OrderBook $depth, $bookSide){
        if (count($depth->bids) > 0 && count($depth->asks) > 0) {
            $insideBid = $depth->bids[0];
            $insideAsk = $depth->asks[0];

            if ($insideBid instanceof DepthItem && $insideAsk instanceof DepthItem) {
                switch($bookSide){
                    case OrderType::BUY:
                        return $insideBid->price;
                    case OrderType::SELL:
                        return $insideAsk->price;
                }
            }
        }

        return null;
    }
}

