<?php

namespace CryptoMarket\Account;

use CryptoMarket\Account\MultiSourcedAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;

class BitcoinCashAddress extends MultiSourcedAccount
{
    private $address;

    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return ExchangeName::BitcoinCash;
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
                $raw = CurlHelper::query("https://api.blocktrail.com/v1/bcc/address/$addr?api_key=MY_APIKEY");
                return $raw['balance'] / pow(10, 8);
            },
            function ($addr)
            {
                $raw = CurlHelper::query("https://blockdozer.com/insight-api/addr/$addr?noTxList=1");
                return $raw['balance'];
            },
            function ($addr)
            {
                $raw = CurlHelper::query("https://bitcoincash.blockexplorer.com/api/addr/$addr?noTxList=1");
                return $raw['balance'];
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::BCH;
    }
}

