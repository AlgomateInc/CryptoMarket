<?php

namespace CryptoMarket\AccountLoader;

use CryptoMarket\AccountLoader\IAccountLoader;

use CryptoMarket\Account\BitcoinAddress;
use CryptoMarket\Account\BitcoinCashAddress;
use CryptoMarket\Account\BitcoinGoldAddress;
use CryptoMarket\Account\EthereumAccount;
use CryptoMarket\Account\EthereumClassicAccount;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Bitfinex;
use CryptoMarket\Exchange\Bitstamp;
use CryptoMarket\Exchange\Bittrex;
use CryptoMarket\Exchange\BitVC;
use CryptoMarket\Exchange\GDAX;
use CryptoMarket\Exchange\Gemini;
use CryptoMarket\Exchange\Kraken;
use CryptoMarket\Exchange\Poloniex;
use CryptoMarket\Exchange\WEX;
use CryptoMarket\Exchange\Yunbi;

class ConfigAccountLoader implements IAccountLoader
{
    private $accountsConfig;

    public function __construct($accountsConfig)
    {
        $this->accountsConfig = $accountsConfig;
    }

    public function getConfig($privateKey = null)
    {
        return $this->accountsConfig;
    }

    protected function getMarketObjects($accountsConfig, $mktFilter)
    {
        $accounts = array();

        foreach ($accountsConfig as $mktName => $mktConfig){

            //filter to specific exchanges, as specified
            if ($mktFilter != null) {
                if (!in_array($mktName, $mktFilter)) {
                    continue;
                }
            }

            switch ($mktName)
            {
                case ExchangeName::Binance:
                    $accounts[ExchangeName::Binance] = new Binance(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Bitstamp:
                    $accounts[ExchangeName::Bitstamp] = new Bitstamp(
                        $mktConfig['custid'],
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Bitfinex:
                    $accounts[ExchangeName::Bitfinex] = new Bitfinex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Bittrex:
                    $accounts[ExchangeName::Bittrex] = new Bittrex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Gemini:
                    $accounts[ExchangeName::Gemini] = new Gemini(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::BitVC:
                    $accounts[ExchangeName::BitVC] = new BitVC(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Poloniex:
                    $accounts[ExchangeName::Poloniex] = new Poloniex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Kraken:
                    $accounts[ExchangeName::Kraken] = new Kraken(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::GDAX:
                    $accounts[ExchangeName::GDAX] = new GDAX(
                        $mktConfig['key'],
                        $mktConfig['secret'],
                        $mktConfig['passphrase']
                    );
                    break;

                case ExchangeName::WEX:
                    $accounts[ExchangeName::WEX] = new WEX(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Yunbi:
                    $accounts[ExchangeName::Yunbi] = new Yunbi(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Bitcoin:
                    $accounts[ExchangeName::Bitcoin] = new BitcoinAddress(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::BitcoinCash:
                    $accounts[ExchangeName::BitcoinCash] = new BitcoinCashAddress(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::BitcoinGold:
                    $accounts[ExchangeName::BitcoinGold] = new BitcoinGoldAddress(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::Ethereum:
                    $accounts[ExchangeName::Ethereum] = new EthereumAccount(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::EthereumClassic:
                    $accounts[ExchangeName::EthereumClassic] = new EthereumClassicAccount(
                        $mktConfig['address']
                    );
                    break;

            }
        }

        return $accounts;
    }

    public function getAccounts(array $mktFilter = null, $privateKey = null)
    {
        return $this->getMarketObjects($this->accountsConfig, $mktFilter);
    }
}

