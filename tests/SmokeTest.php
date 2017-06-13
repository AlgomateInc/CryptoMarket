<?php
/**
 * User: jon
 * Date: 1/17/2017
 * Time: 8:00 PM
 */

namespace CryptoMarket\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\AccountConfigData;
use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Gdax;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;

class SmokeTest extends TestCase
{
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader(AccountConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Gdax));
        $this->mkt = $exchanges[ExchangeName::Gdax];
        $this->mkt->init();
    }

    public function testSupportedPairs()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        $known_pairs = array("BTCGBP","BTCEUR","ETHUSD","ETHBTC","LTCUSD", "LTCBTC", "BTCUSD");
        foreach ($known_pairs as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
        $known_pairs_slash = array("BTC/GBP","BTC/EUR","ETH/USD","ETH/BTC","LTC/USD", "LTC/BTC", "BTC/USD");
        foreach ($known_pairs_slash as $pair) {
            $this->assertTrue($this->mkt->supports($pair));
        }
    }

    public function testPrecisions()
    {
        $this->assertTrue($this->mkt instanceof Gdax);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $precision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $this->assertEquals($ticker->bid, round($ticker->bid, $precision));
            $this->assertEquals($ticker->ask, round($ticker->ask, $precision));
            $this->assertEquals($ticker->last, round($ticker->last, $precision));
        }
    }

}

