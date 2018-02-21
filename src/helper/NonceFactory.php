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

    public function __construct($micro = true){
        if ($micro) {
            $this->noncetime = intval(DateHelper::totalMicrotime());
        } else {
            // Wex makes things weird, where the nonce can only be a small value
            $UNIX_TIME_20180101 = 1514764800;
            $this->noncetime = time() - $UNIX_TIME_20180101;
        }
    }

    public function get(){
        return $this->noncetime + (++$this->nonce);
    }
} 

