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
    protected abstract function getAddressList();
    protected abstract function getBalanceFunctions();
    protected abstract function getCurrencyName();

    public function balances()
    {
        $totalBalance = 0;
        foreach ($this->getAddressList() as $addy)
        {
            $addy = trim($addy);

            $bal = $this->getBalance($addy);
            $totalBalance = strval($totalBalance + $bal);
        }

        $balances = array();
        $balances[$this->getCurrencyName()] = $totalBalance;
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

