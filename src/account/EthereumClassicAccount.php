<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 12/28/2016
 * Time: 11:47 PM
 */

namespace CryptoMarket\Account;

use CryptoMarket\Account\MultiSourcedAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;

class EthereumClassicAccount extends MultiSourcedAccount
{
    private $address;

    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return ExchangeName::EthereumClassic;
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
                $raw = CurlHelper::query('https://etcchain.com/api/v1/getAddressBalance?address=' . trim($addr));
                return $raw['balance'];
            },
            function ($addr)
            {
                $raw = CurlHelper::query('https://api.gastracker.io/addr/' . trim($addr));
                return $raw['balance']['ether'];
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::ETC;
    }
}

