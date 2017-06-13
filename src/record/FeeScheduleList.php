<?php

namespace CryptoMarket\Record;

use CryptoMarket\Record\FeeScheduleItem;
use CryptoMarket\Record\TradingRole;

class FeeScheduleList
{
    public $fees; // array of fees

    public function __construct()
    {
        $this->fees = array();
    }

    public function push($feeScheduleItem)
    {
        array_push($this->fees, $feeScheduleItem);
    }

    public function getFee($volume, $tradingRole)
    {
        foreach($this->fees as $feeScheduleItem) {
            if ($volume >= $feeScheduleItem->lowerRange &&
                $volume < $feeScheduleItem->upperRange) {
                if ($tradingRole == TradingRole::Maker) {
                    return $feeScheduleItem->makerFee;
                } else if ($tradingRole == TradingRole::Taker) {
                    return $feeScheduleItem->takerFee;
                }
            }
        }

        throw new \Exception("No fee found for $volume, $tradingRole");
    }
}
