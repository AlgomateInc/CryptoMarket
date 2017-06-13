<?php

namespace CryptoMarket\Record;

use CryptoMarket\Record\FeeScheduleItem;
use CryptoMarket\Record\FeeScheduleList;

class FeeSchedule
{
    public $fallbackFees; // generic fallback, in %
    public $pairFees = array(); // assoc pair=>array(FeeScheduleItem)

    public function setFallbackFee($takerFee, $makerFee)
    {
        $this->fallbackFees = new FeeScheduleList();
        $this->fallbackFees->push(FeeScheduleItem::newWithoutRange($takerFee, $makerFee));
    }

    public function setFallbackFees($genericFeeSchedule)
    {
        $this->fallbackFees = $genericFeeSchedule;
    }

    public function replacePairFee($pair, $takerFee, $makerFee)
    {
        if (!array_key_exists($pair, $this->pairFees)) {
            throw new \Exception("Pair $pair not present");
        }
        $this->pairFees[$pair] = new FeeScheduleList();
        $this->pairFees[$pair]->push(FeeScheduleItem::newWithoutRange($takerFee, $makerFee));
    }

    public function addPairFee($pair, $takerFee, $makerFee)
    {
        if (array_key_exists($pair, $this->pairFees)) {
            throw new \Exception("Pair $pair added twice");
        }
        $this->pairFees[$pair] = new FeeScheduleList();
        $this->pairFees[$pair]->push(FeeScheduleItem::newWithoutRange($takerFee, $makerFee));
    }

    public function addPairFees($pair, $feeScheduleList)
    {
        if (array_key_exists($pair, $this->pairFees)) {
            throw new \Exception("Pair $pair added twice");
        }
        $this->pairFees[$pair] = $feeScheduleList;
    }

    public function getFee($pair, $tradingRole, $volume = 0.0)
    {
        if (!empty($this->pairFees) && array_key_exists($pair, $this->pairFees)) {
            return $this->pairFees[$pair]->getFee($volume, $tradingRole);
        } else if (isset($this->fallbackFees)) {
            return $this->fallbackFees->getFee($volume, $tradingRole);
        } else {
            throw new \Exception("Pair $pair has no fees associated");
        }
    }

    public function isEmpty()
    {
        return !isset($this->fallbackFees) && empty($this->pairFees);
    }
}

