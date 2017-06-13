<?php

namespace CryptoMarket\Record;

class OrderCancel
{
    public $orderId;
    public $exchange;
    public $strategyOrderId;

    /**
     * OrderCancel constructor.
     * @param $orderId
     * @param $exchange
     * @param $strategyOrderId
     */
    public function __construct($orderId, $exchange, $strategyOrderId)
    {
        $this->orderId = $orderId;
        $this->exchange = $exchange;
        $this->strategyOrderId = $strategyOrderId;
    }
}

