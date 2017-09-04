<?php

namespace CryptoMarket\Record;

class Transaction{
    /** @var string */
    public $exchange;
    /** @var int or string */
    public $id;
    /** @var string */
    public $type;
    /** @var string */
    public $currency;
    /** @var float */
    public $amount;
    /** @var int or MongoDB\BSON\UTCDateTime */
    public $timestamp;

    private function fail($name, $value) {
        printf("$name failed: ");
        var_dump($value);
        return false;
    }

    public function isValid()
    {
        if (!is_string($this->exchange) || $this->exchange == "") {
            return $this->fail('Exchange', $this->exchange);
        }

        if (is_int($this->id)) {
            if ($this->id == 0) {
                return $this->fail('Id', $this->id);
            }
        } else if (is_string($this->id)) {
            if ($this->id == "") {
                return $this->fail('Id', $this->id);
            }
        } else {
            return $this->fail('Id', $this->id);
        }

        if (!is_string($this->type) ||
            ($this->type != TransactionType::Credit && $this->type != TransactionType::Debit)) {
            return $this->fail('Type', $this->type);
        }

        if (!is_string($this->currency) || $this->currency == "") {
            return $this->fail('Currency', $this->currency);
        }

        if (!is_float($this->amount) || $this->amount == 0.0) {
            return $this->fail('Amount', $this->amount);
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

