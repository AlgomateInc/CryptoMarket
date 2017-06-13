<?php

namespace CryptoMarket\Record;

class FeeScheduleItem
{
    public $lowerRange; // start of volume where fee applies
    public $upperRange; // end of volume where fee applies
    public $takerFee; // fee, in %
    public $makerFee; // fee, in %

    public function __construct($lowerRange, $upperRange, $takerFee, $makerFee)
    {
        $this->lowerRange = $lowerRange;
        $this->upperRange = $upperRange;
        $this->takerFee = $takerFee;
        $this->makerFee = $makerFee;
    }

    public static function newWithoutRange($takerFee, $makerFee)
    {
        return new FeeScheduleItem(0.0, INF, $takerFee, $makerFee);
    }

    public static function newWithoutRole($lowerRange, $upperRange, $fee)
    {
        return new FeeScheduleItem($lowerRange, $upperRange, $fee, $fee);
    }
}

