<?php
/**
 * User: jon
 * Date: 1/17/2017
 * Time: 8:00 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Gdax;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class GdaxTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Gdax));
        self::$mkt = $exchanges[ExchangeName::Gdax];
        self::$mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
    }

    public function testSupportedPairs()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $known_pairs = array("BTCGBP","BTCEUR","ETHUSD","ETHBTC","LTCUSD", "LTCBTC", "BTCUSD");
        foreach ($known_pairs as $pair) {
            $this->assertTrue(self::$mkt->supports($pair));
        }
        $known_pairs_slash = array("BTC/GBP","BTC/EUR","ETH/USD","ETH/BTC","LTC/USD", "LTC/BTC", "BTC/USD");
        foreach ($known_pairs_slash as $pair) {
            $this->assertTrue(self::$mkt->supports($pair));
        }
    }

    public function testPrecisions()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $precision = self::$mkt->quotePrecision($pair, $ticker->bid);
            $this->assertEquals($ticker->bid, round($ticker->bid, $precision));
            $this->assertEquals($ticker->ask, round($ticker->ask, $precision));
            $this->assertEquals($ticker->last, round($ticker->last, $precision));
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $this->markTestSkipped();
        $availablePairsInUSA = array("ETHUSD","ETHBTC","LTCUSD", "LTCBTC", "BTCUSD");
        foreach ($availablePairsInUSA as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $quotePrecision = self::$mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);
            $minOrder = self::$mkt->minimumOrderSize($pair, $price);
            $ret = self::$mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
        }
    }

    public function testBasePrecision()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $this->markTestSkipped();
        $availablePairsInUSA = array("ETHUSD","ETHBTC","LTCUSD", "LTCBTC", "BTCUSD");
        foreach ($availablePairsInUSA as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $quotePrecision = self::$mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);

            $minOrder = self::$mkt->minimumOrderSize($pair, $price);
            $basePrecision = self::$mkt->basePrecision($pair, $ticker->bid);
            $minOrder += bcpow(10, -1 * $basePrecision, $basePrecision);

            $ret = self::$mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
            sleep(1);
        }
    }

    public function testBalances()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $currencies = self::$mkt->supportedCurrencies();
        $ret = self::$mkt->balances();
        foreach($ret as $curr=>$amt) {
            $this->assertTrue(in_array($curr, $currencies));
            $this->assertTrue(is_numeric($amt));
        }
        $this->assertNotEmpty($ret);
    }

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $this->assertEquals(0.1, self::$mkt->tradingFee(CurrencyPair::BTCEUR, TradingRole::Taker, 10000.0));
        sleep(1);
        $this->assertEquals(0.30, self::$mkt->tradingFee(CurrencyPair::BTCEUR, TradingRole::Taker, 10.0));
        sleep(1);
        $this->assertEquals(0.0, self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Maker, 0.1));
        sleep(1);
        $this->assertEquals(0.0, self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Maker, 100000000000.0));
        sleep(1);
        $this->assertEquals(0.30, self::$mkt->tradingFee(CurrencyPair::ETHUSD, TradingRole::Taker, 10.0));
    }

    public function testUserFees()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $this->assertEquals(0.30, self::$mkt->currentTradingFee(CurrencyPair::BTCEUR, TradingRole::Taker));
        sleep(1);
        $this->assertEquals(0.0, self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Maker));
        sleep(1);
        $this->assertEquals(0.30, self::$mkt->currentTradingFee(CurrencyPair::ETHUSD, TradingRole::Taker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $schedule = self::$mkt->currentFeeSchedule();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
        }
    }

    public function testBuyOrderSubmission()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $ret = self::$mkt->buy(CurrencyPair::BTCUSD, 0.01, 1000.00);
        $this->checkAndCancelOrder($ret);
    }

    public function testSellOrderSubmission()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $ret = self::$mkt->sell(CurrencyPair::BTCUSD, 0.01, 1000000);
        $this->checkAndCancelOrder($ret);
    }

    public function testMyTrades()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $res = self::$mkt->tradeHistory(1000);
        $this->assertNotNull($res);
        // because of pagination, make sure we get over 100
        $this->assertTrue(count($res) > 100);
    }

    public function testExecutions()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $this->markTestSkipped();
        $ret = self::$mkt->submitMarketOrder('sell', CurrencyPair::BTCUSD, 0.01);
        sleep(1);
        $exec = self::$mkt->getOrderExecutions($ret);
        $this->assertTrue(count($exec) > 0);
        $ret = self::$mkt->submitMarketOrder('buy', CurrencyPair::BTCUSD, 0.01);
        sleep(1);
        $exec = self::$mkt->getOrderExecutions($ret);
        $this->assertTrue(count($exec) > 0);
    }

    public function testTransactions()
    {
        $this->assertTrue(self::$mkt instanceof Gdax);
        $transactions = self::$mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Gdax", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    public function testDepth()
    {
        $depth = self::$mkt->depth(CurrencyPair::BTCUSD);
        $this->assertNotNull($depth);
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue(self::$mkt->isOrderAccepted($response));
        $this->assertTrue(self::$mkt->isOrderOpen($response));

        $this->assertNotNull(self::$mkt->cancel($response['id']));
        sleep(1);
        $this->assertFalse(self::$mkt->isOrderOpen($response));
    }
}

