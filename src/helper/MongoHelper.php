<?php

namespace CryptoMarket\Helper;

class MongoHelper
{
    // Mongo timestamps are in milliseconds, while PHP timestamps are in seconds
    static function mongoDateOfPHPDate($datetime)
    {
        return $datetime * 1000;
    }
}

