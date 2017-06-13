<?php

namespace CryptoMarket\Record;

class Transaction{
    /** @var string */
    public $exchange;
    /** @var int */
    public $id;
    /** @var string */
    public $type;
    /** @var string */
    public $currency;
    /** @var float */
    public $amount;
    /** @var int or MongoDB\BSON\UTCDateTime */
    public $timestamp;

    public function isValid()
    {
        if (!is_string($this->exchange) || $this->exchange == "") {
            printf("Exchange is empty: ");
            var_dump($this->exchange);
            return false;
        }

        if (!is_int($this->id) || $this->id == 0) {
            printf("Id is empty: ");
            var_dump($this->id);
            return false;
        }

        if (!is_string($this->type) ||
            ($this->type != TransactionType::Credit && $this->type != TransactionType::Debit)) {
            printf("Type is invalid: ");
            var_dump($this->type);
            return false;
        }

        if (!is_string($this->currency) || $this->currency == "") {
            printf("Currency is invalid: ");
            var_dump($this->currency);
            return false;
        }

        if (!is_float($this->amount) || $this->amount == 0.0) {
            printf("Amount is empty: ");
            var_dump($this->amount);
            return false;
        }

        /* Need to make sure that timestamps are always the same type first
        if (!is_int($this->timestamp) || $this->timestamp == 0) {
            printf("Timestamp is empty");
            return false;
        }
         */

        return true;
    }
}

