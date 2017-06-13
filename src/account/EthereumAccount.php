<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/1/2016
 * Time: 3:29 AM
 */

namespace CryptoMarket\Account;

use CryptoMarket\Account\MultiSourcedAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Record\Currency;

class EthereumAccount extends MultiSourcedAccount
{
    private $address;
    private $tokenContracts = array();
    private $tokenPrecision = array();

    /**
     * EthereumAccount constructor.
     */
    public function __construct($address)
    {
        $this->address = explode(',', $address);
        $this->tokenContracts['GNT'] = '0xa74476443119A942dE498590Fe1f2454d7D4aC0d';
        $this->tokenContracts['TRST'] = '0xCb94be6f13A1182E4A4B6140cb7bf2025d28e41B';

        $this->tokenPrecision['GNT'] = 18;
        $this->tokenPrecision['TRST'] = 6;
    }

    public function Name()
    {
        return ExchangeName::Ethereum;
    }

    public function balances()
    {
        $balances = parent::balances();

        //get token balances
        foreach ($this->tokenContracts as $tokenName => $tokenContract)
        {
            $tokenBalance = 0;

            foreach($this->getAddressList() as $addy)
            {
                $raw = CurlHelper::query("https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress=$tokenContract&address=" . trim($addy));
                $tokenBalance += $raw['result'] / pow(10, $this->tokenPrecision[$tokenName]);
            }

            $balances[$tokenName] = $tokenBalance;
        }

        return $balances;
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
                $raw = CurlHelper::query('https://api.etherscan.io/api?module=account&action=balance&address=' . trim($addr));
                return $raw['result'] / pow(10, 18);
            },
            function ($addr)
            {
                $raw = CurlHelper::query('https://etherchain.org/api/account/' . trim($addr));
                return $raw['data'][0]['balance'] / pow(10, 18);
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::ETH;
    }
}

