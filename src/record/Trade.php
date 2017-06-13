<?php

namespace CryptoMarket\Record;

class Trade
{
    /** @var int */
    public $tradeId;
    /** @var int */
    public $orderId;
    /** @var string */
    public $exchange;
    /** @var string */
    public $currencyPair;
    /** @var string */
    public $orderType;
    /** @var float */
    public $price;
    /** @var float */
    public $quantity;
    /** @var int or MongoDB\BSON\UTCDateTime */
    public $timestamp;

    public function isValid()
    {
        if (!is_int($this->tradeId) || $this->tradeId == 0) {
            printf("tradeId is empty: ");
            var_dump($this->tradeId);
            return false;
        }

        if (!is_int($this->orderId) || $this->orderId == 0) {
            printf("orderId is empty: ");
            var_dump($this->orderId);
            return false;
        }

        if (!is_string($this->exchange) || $this->exchange == "") {
            printf("Exchange is empty: ");
            var_dump($this->exchange);
            return false;
        }

        if (!is_string($this->currencyPair) || $this->currencyPair == "") {
            printf("currencyPair is empty: ");
            var_dump($this->currencyPair);
            return false;
        }

        if (!is_string($this->orderType) || $this->orderType == "") {
            printf("orderType is empty: ");
            var_dump($this->orderType);
            return false;
        }

        if (!is_float($this->price) || $this->price == 0.0) {
            printf("price is empty: ");
            var_dump($this->price);
            return false;
        }

        if (!is_float($this->quantity) || $this->quantity == 0.0) {
            printf("quantity is empty: ");
            var_dump($this->quantity);
            return false;
        }

        /* Need to make sure that timestamps are always the same type first
        if (!is_int($this->timestamp) || $this->timestamp == 0) {
            printf("timestamp is empty: ");
            var_dump($this->timestamp);
            return false;
        }
         */
        return true;
    }
}

