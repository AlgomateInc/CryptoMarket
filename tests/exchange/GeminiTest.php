<?php

/**
 * User: Jon
 * Date: 3/10/2017
 * Time: 16:00
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Gemini;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;

class GeminiTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Gemini));
        self::$mkt = $exchanges[ExchangeName::Gemini];
        self::$mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
    }

    public function testPrecisions()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        $this->assertEquals(2, self::$mkt->quotePrecision(CurrencyPair::BTCUSD, 1));
        $this->assertEquals(2, self::$mkt->quotePrecision(CurrencyPair::ETHUSD, 1));
        $this->assertEquals(5, self::$mkt->quotePrecision(CurrencyPair::ETHBTC, 1));
    }

    public function testBalances()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        $ret = self::$mkt->balances();
        $this->assertNotEmpty($ret);
    }

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        $this->assertEquals(0.25, self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 1000.0));
        $this->assertEquals(0.15, self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 10000.0));
        $this->assertEquals(0.25, self::$mkt->tradingFee(CurrencyPair::ETHUSD, TradingRole::Taker, 10.0));
        $this->assertEquals(0.15, self::$mkt->tradingFee(CurrencyPair::ETHUSD, TradingRole::Taker, 200000.0));
    }

    public function testUserFees()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        $this->assertEquals(0.25, self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker));
        $this->assertEquals(0.25, self::$mkt->currentTradingFee(CurrencyPair::ETHUSD, TradingRole::Maker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        $schedule = self::$mkt->currentFeeSchedule();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            sleep(1);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
            sleep(1);
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $quotePrecision = self::$mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);
            $minOrder = self::$mkt->minimumOrderSize($pair, $price);

            $ret = self::$mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
            sleep(1);
        }
    }

    public function testBasePrecision()
    {
        $this->assertTrue(self::$mkt instanceof Gemini);
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
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

    public function testBuyOrderSubmission()
    {
        if (self::$mkt instanceof Gemini)
        {
            $response = self::$mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if (self::$mkt instanceof Gemini)
        {
            $response = self::$mkt->sell(CurrencyPair::BTCUSD, 0.01, 20000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if (self::$mkt instanceof Gemini)
        {
            $res = self::$mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if (self::$mkt instanceof Gemini)
        {
            $res = self::$mkt->trades(CurrencyPair::BTCUSD, time()-60);
            $this->assertNotNull($res);
        }
    }

    public function testTickers()
    {
        $res = self::$mkt->tickers();
        var_dump($res);
        $this->assertNotNull($res);
    }

    public function testDepth()
    {
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $res = self::$mkt->depth($pair);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue(self::$mkt->isOrderAccepted($response));
        $this->assertTrue(self::$mkt->isOrderOpen($response));

        $this->assertNotNull(self::$mkt->cancel($response['order_id']));
        $this->assertFalse(self::$mkt->isOrderOpen($response));
    }
}
 
