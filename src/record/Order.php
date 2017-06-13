<?php

namespace CryptoMarket\Record;

use CryptoMarket\Record\OrderType;

class Order{
    public $currencyPair;
    public $exchange;
    public $orderType = OrderType::BUY;
    public $limit = 0;
    public $quantity = 0;
}

