<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/2/2016
 * Time: 5:21 AM
 */

namespace CryptoMarket\Account;

use CryptoMarket\Account\MultiSourcedAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;

class BitcoinAddress extends MultiSourcedAccount
{
    private $address;

    /**
     * BitcoinAddress constructor.
     */
    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return ExchangeName::Bitcoin;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    protected function getAddressList()
    {
        return $this->address;
    }

    protected function getBalanceFunctions()
    {
        return array(
            function ($addr)
            {
                $raw = CurlHelper::query("https://blockchain.info/rawaddr/$addr?limit=0&format=json");
                return $raw['final_balance'] / pow(10, 8);
            },
            function ($addr)
            {
                $raw = CurlHelper::query("https://blockexplorer.com/api/addr/$addr?noTxList=1");
                return $raw['balance'];
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::BTC;
    }
}

