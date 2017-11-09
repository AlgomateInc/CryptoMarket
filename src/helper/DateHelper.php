<?php

namespace CryptoMarket\Helper;

class DateHelper
{
    static function totalMicrotime()
    {
        return microtime(true) * 1000000;
    }

    // Mongo timestamps are in milliseconds, while PHP timestamps are in seconds
    static function mongoDateOfPHPDate($datetime)
    {
        return $datetime * 1000;
    }
}
