<?php

/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 10:39 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Bitstamp;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class BitstampTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Bitstamp));
        self::$mkt = $exchanges[ExchangeName::Bitstamp];
        self::$mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
    }

    public function testDepth()
    {
        $depth = self::$mkt->depth(CurrencyPair::XRPEUR);
        $this->assertNotEmpty($depth);
    }

    public function testTickers()
    {
        $currs = self::$mkt->supportedCurrencyPairs();
        foreach ($currs as $curr) {
            $ticker = self::$mkt->ticker($curr);
            $this->assertNotEmpty($ticker);
            $this->assertTrue($ticker instanceof Ticker);
        }
    }

    public function testBalances()
    {
        $balances = self::$mkt->balances();
        $currs = self::$mkt->supportedCurrencies();
        foreach ($currs as $curr) {
            $this->assertArrayHasKey($curr, $balances);
        }
    }

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $this->markTestSkipped();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $this->assertEquals(0.25, self::$mkt->currentTradingFee($pair, TradingRole::Taker));
            $this->assertEquals(0.13, self::$mkt->tradingFee($pair, TradingRole::Taker, 1.1e6));
        }
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $schedule = self::$mkt->currentFeeSchedule();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $this->markTestSkipped();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
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
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $this->markTestSkipped();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $quotePrecision = self::$mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);

            $minOrder = self::$mkt->minimumOrderSize($pair, $price);
            $basePrecision = self::$mkt->basePrecision($pair, $ticker->bid);
            $minOrder += bcpow(10, -1 * $basePrecision, $basePrecision);

            $ret = self::$mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
        }
    }

    public function testBTCUSDOrder()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $response = self::$mkt->sell(CurrencyPair::BTCUSD, 0.01, 100000);
        $this->checkAndCancelOrder($response);
    }

    public function testBTCEUROrder()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $response = self::$mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->checkAndCancelOrder($response);
    }

    public function testActiveOrders()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $response = self::$mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->assertNotEmpty(self::$mkt->activeOrders());
        $this->assertTrue(self::$mkt->isOrderOpen($response));
        $this->checkAndCancelOrder($response);
    }

    public function testOrderExecutions()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $response = self::$mkt->buy(CurrencyPair::BTCEUR, 0.01, 100000);
        sleep(1);
        $exs = self::$mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
        $response = self::$mkt->sell(CurrencyPair::BTCEUR, 0.01, 1);
        sleep(1);
        $exs = self::$mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
    }

    public function testTransactions()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $transactions = self::$mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Bitstamp", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    public function testTradeHistory()
    {
        $this->assertTrue(self::$mkt instanceof Bitstamp);
        $history = self::$mkt->tradeHistory();
        foreach ($history as $trade) {
            $this->assertEquals("Bitstamp", $trade->exchange);
            $this->assertTrue($trade instanceof Trade);
            $this->assertTrue($trade->isValid());
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);
        $this->assertTrue(self::$mkt->isOrderAccepted($response));
        $this->assertTrue(self::$mkt->cancel($response['id']));
    }
}
 
