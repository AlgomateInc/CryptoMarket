<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/2/13
 * Time: 10:19 AM
 */

namespace CryptoMarket\Exchange;

class NonceFactory {

    private $noncetime;
    private $nonce = 0;

    public function __construct(){
        $mt = explode(' ', microtime());
        $this->noncetime = $mt[1];
    }

    public function get(){
        return $this->noncetime + (++$this->nonce);
    }
} 

