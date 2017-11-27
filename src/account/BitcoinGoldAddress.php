<?php

namespace CryptoMarket\Account;

use CryptoMarket\Account\MultiSourcedAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;

class BitcoinGoldAddress extends MultiSourcedAccount
{
    private $address;

    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return ExchangeName::BitcoinGold;
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
                $raw = CurlHelper::query("https://btgblocks.com/ext/getbalance/$addr");
                return $raw;
            },
            function ($addr)
            {
                $raw = CurlHelper::query("https://btgexp.com/ext/getbalance/$addr");
                return $raw;
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::BTG;
    }
}

