<?php

namespace CryptoMarket\AccountLoader;

use CryptoMarket\AccountLoader\IAccountLoader;

use MongoDB\Client;

class MongoAccountLoader extends ConfigAccountLoader
{
    private $mongo;
    private $mdb;

    private $serverName = null;
    private $unencryptedConfig = array();
    private $encryptedConfig = array();

    public function __construct(
        $mongodbUri,
        $mongodbName,
        $accountsConfig,
        $serverName)
    {
        parent::__construct($accountsConfig);

        $this->mongo = new Client($mongodbUri);
        $this->mdb = $this->mongo->selectDatabase($mongodbName);
        $this->serverName = $serverName;
    }

    private function addUnencryptedConfig($mktConfig)
    {
        //rework the exchange settings to expected, legacy, format used
        //by ConfigAccountLoader, which expects an associative array
        foreach ($mktConfig as $mktSetItem) {
            $this->unencryptedConfig[$mktSetItem['Name']] = $mktSetItem['Settings'];
        }
    }

    private function addEncryptedConfig($mktConfig, $privateKey)
    {
        foreach ($mktConfig as $mktSetItem) {
            $dataString = base64_decode($mktSetItem['Data']);
            openssl_private_decrypt($dataString, $decryptedString, $privateKey);
            $this->encryptedConfig[$mktSetItem['Name']] = json_decode($decryptedString, true); // return as array
        }
    }

    private function loadAccountConfig($serverName, $privateKey)
    {
        $serverAccounts = $this->mdb->servers;

        //find the config for this server
        $cursor = $serverAccounts->find(['ServerName' => $serverName]);
        $unencryptedDbConfig = null;
        $encryptedDbConfig = null;

        foreach ($cursor as $dbSettings) {
            if (array_key_exists('ExchangeSettings', $dbSettings)) {
                $unencryptedDbConfig = $dbSettings['ExchangeSettings'];
            }
            if (array_key_exists('ServerExchangeSettings', $dbSettings)) {
                $encryptedDbConfig = $dbSettings['ServerExchangeSettings'];
            }
        }

        if (isset($unencryptedDbConfig)) {
            $this->unencryptedConfig = array();
            $this->addUnencryptedConfig($unencryptedDbConfig);
        }
        if (isset($privateKey) && isset($encryptedDbConfig)) {
            $this->encryptedConfig = array();
            $this->addEncryptedConfig($encryptedDbConfig, $privateKey);
        }

        foreach (array_intersect_key($this->encryptedConfig, $this->unencryptedConfig) as $mktName => $mktSettings) {
            printf("MongoAccountLoader: Encrypted config for $mktName overrides unencrypted\n");
        }
    }

    public function getConfig($privateKey = null)
    {
        $this->loadAccountConfig($this->serverName, $privateKey);
        // merge all arrays together
        $merged = array_replace($this->unencryptedConfig, $this->encryptedConfig);
        if (empty($merged)) {
            return parent::getConfig($privateKey);
        } else {
            return $merged;
        }
    }

    public function getAccounts(array $mktFilter = null, $privateKey = null)
    {
        return $this->getMarketObjects($this->getConfig($privateKey), $mktFilter);
    }
}

