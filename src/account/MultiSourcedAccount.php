<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 11/20/2016
 * Time: 2:30 AM
 */

namespace CryptoMarket\Account;

use CryptoMarket\Account\IAccount;

abstract class MultiSourcedAccount implements IAccount
{
    /**
     * Provides a list of addresses to get balances for, used in the
     * "balances" function.  Could be a bitcoin, ethereum, or otherwise address
     */
    protected abstract function getAddressList();

    /**
     * Provides a list of functions that can get the balance at a particular
     * address, used in "getBalance", which goes through each function until
     * one of them succeeds
     */
    protected abstract function getBalanceFunctions();
    
    /**
     * Used to tag the balance with a proper currency when calling "balances"
     */
    protected abstract function getCurrencyName();

    public function balances()
    {
        $totalBalance = 0;
        foreach ($this->getAddressList() as $addy)
        {
            $addy = trim($addy);
            $totalBalance += $this->getBalance($addy);
        }

        $balances = array();
        $balances[$this->getCurrencyName()] = strval($totalBalance);
        return $balances;
    }

    function getBalance($addr)
    {
        $functions = $this->getBalanceFunctions();
        static $marketIndex = 0;

        $val = null;
        for ($i = 0; $i < count($functions); $i++) {
            try{
                $val = $functions[($marketIndex + $i) % count($functions)]($addr);
                $marketIndex = ($marketIndex + 1) % count($functions);
                break;
            } catch (\Exception $e) {}
        }

        if ($val === null)
            throw new \Exception("Could not get address balance for $addr");

        return $val;
    }
}

