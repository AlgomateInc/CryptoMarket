<?php

/**
 * User: jon
 * Date: 4/24/2018
 */

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;
use CryptoMarket\AccountLoader\MongoAccountLoader;

use MongoDB\Client;

class MongoAccountLoaderTest extends TestCase
{
    private $mongoAccountLoader;
    private $mdb;
    private $mongo;

    public function setUp()
    {
        $this->mongoAccountLoader = new MongoAccountLoader(
            ConfigData::MONGODB_URI,
            ConfigData::MONGODB_DBNAME,
            ConfigData::ACCOUNTS_CONFIG,
            null);
        $this->mongo = new Client(ConfigData::MONGODB_URI);
        $this->mdb = $this->mongo->selectDatabase(ConfigData::MONGODB_DBNAME);
    }

    public function testGetConfig()
    {
        $configuredExchanges = $this->mongoAccountLoader->getConfig(null);
        $this->assertNotNull($configuredExchanges);
    }

    public function testChangeConfig()
    {
        $dbConfig = [ "ServerName" => null, 
            "ExchangeSettings" => [ 
            [ "Name" => "WEX", "AuthType" => 1, "Settings" => [ "key" => "blah", "secret" => "blah" ] ],
            [ "Name" => "Kraken", "AuthType" => 1, "Settings" => [ "key"=> "blahblah", "secret" => "blahblah" ] ], 
            [ "Name" => "Bitfinex", "AuthType" => 1, "Settings" => [ "key" => "morekey", "secret" => "moresecret" ] ] 
        ] ];
        $this->mdb->servers->deleteOne(['ServerName' => null]);
        $configuredExchanges = $this->mongoAccountLoader->getConfig(null);
        $this->assertNotNull($configuredExchanges);
        $this->mdb->servers->insertOne($dbConfig);
        $reconfiguredExchanges = $this->mongoAccountLoader->getConfig(null);
        $this->assertNotNull($reconfiguredExchanges);
        $this->assertNotEquals($configuredExchanges, $reconfiguredExchanges);
    }
}
