<?php

/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/20/2014
 * Time: 12:12 AM
 */

namespace CryptoMarket\Exchange;

require_once('IExchange.php');

interface IMarginExchange extends IExchange {
    public function long($pair, $quantity, $price);
    public function short($pair, $quantity, $price);

    public function positions();
} 

