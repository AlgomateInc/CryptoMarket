<?php

namespace CryptoMarket\Record;

class ActiveOrder{
    public $order;
    public $marketResponse;
    public $strategyId;
    public $strategyOrderId;
    public $orderId;
    public $executions = array();

    public $marketObj;

    function __sleep()
    {
        return array('order','marketResponse','strategyId', 'strategyOrderId', 'orderId', 'executions');
    }
}

