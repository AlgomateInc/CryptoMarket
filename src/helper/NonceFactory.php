<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/2/13
 * Time: 10:19 AM
 */

namespace CryptoMarket\Helper;

use CryptoMarket\Helper\DateHelper;

class NonceFactory {

    private $noncetime;
    private $nonce = 0;

    public function __construct(){
        $this->noncetime = intval(DateHelper::totalMicrotime());
    }

    public function get(){
        return $this->noncetime + (++$this->nonce);
    }
} 

