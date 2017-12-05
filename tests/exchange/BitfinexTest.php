<?php

/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 12:15 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Bitfinex;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class BitfinexTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Bitfinex));
        self::$mkt = $exchanges[ExchangeName::Bitfinex];
        self::$mkt->init();
    }

    protected function setUp()
    {
        error_reporting(E_ALL);
    }

    public function testTickers()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $tickers = self::$mkt->tickers();
        $supportedPairs = self::$mkt->supportedCurrencyPairs();
        foreach ($supportedPairs as $pair) {
            $found = false;
            foreach ($tickers as $ticker) {
                if ($ticker->currencyPair == $pair) {
                    $found = true;
                }
            }
            $this->assertTrue($found);
        }
        foreach ($tickers as $ticker) {
            $this->assertTrue(in_array($ticker->currencyPair, $supportedPairs));
        }
    }

    public function testPrecision()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $pair = CurrencyPair::BTCUSD;
        $this->assertEquals(4, self::$mkt->quotePrecision($pair, 1.0));
        $this->assertEquals(5, self::$mkt->quotePrecision($pair, 0.1));
        $this->assertEquals(1, self::$mkt->quotePrecision($pair, 1000.0));
        $this->assertEquals(-2, self::$mkt->quotePrecision($pair, 1000000.0));
    }

    public function testBalances()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $ret = self::$mkt->balances();
        $this->assertNotEmpty($ret);
    }

    public function testMinOrders()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
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
        $this->assertTrue(self::$mkt instanceof Bitfinex);
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

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $this->assertEquals('0.2', self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 0.0));
        $this->assertEquals('0.08', self::$mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Maker, 5.0e5));
        $this->assertEquals('0.1', self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Maker));
        $this->assertEquals('0.2', self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
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
        if (self::$mkt instanceof Bitfinex)
        {
            $response = self::$mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if (self::$mkt instanceof Bitfinex)
        {
            $response = self::$mkt->sell(CurrencyPair::BTCUSD, 1, 20000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if (self::$mkt instanceof Bitfinex)
        {
            $res = self::$mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if (self::$mkt instanceof Bitfinex)
        {
            $res = self::$mkt->trades(CurrencyPair::BTCUSD, time()-60);
            $this->assertNotNull($res);
        }
    }

    public function testTransactions()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $transactions = self::$mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Bitfinex", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    public function testPositions()
    {
        $this->assertTrue(self::$mkt instanceof Bitfinex);
        $positions = self::$mkt->positions();
        $this->assertNotNull($positions);
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
 
