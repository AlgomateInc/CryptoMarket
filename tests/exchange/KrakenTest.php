<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 11:21 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Kraken;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class KrakenTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Kraken));
        self::$mkt = $exchanges[ExchangeName::Kraken];
        self::$mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
    }

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $this->assertEquals(0.1, self::$mkt->tradingFee('ZECBTC', TradingRole::Taker, 100000000));
        $this->assertEquals(0.12, self::$mkt->tradingFee('ZECBTC', TradingRole::Maker, 200000));

        $this->assertEquals(0.26, self::$mkt->currentTradingFee('ZECBTC', TradingRole::Taker));
        $this->assertEquals(0.16, self::$mkt->currentTradingFee('ZECBTC', TradingRole::Maker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $schedule = self::$mkt->currentFeeSchedule();
        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
        }
    }

    public function testPrecisions()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $this->markTestSkipped();
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
        $this->assertTrue(self::$mkt instanceof Kraken);
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
        $this->assertTrue(self::$mkt instanceof Kraken);
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

    public function testOrderSubmitAndCancel()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $res = self::$mkt->sell(CurrencyPair::BTCUSD, 0.01, 30000);
        $this->checkAndCancelOrder($res);
    }

    public function testOrderSubmitAndExecute()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $this->markTestSkipped();
        $res = self::$mkt->sell(CurrencyPair::ETHBTC, 0.01, 0.001);
        $this->assertTrue(self::$mkt->isOrderAccepted($res));
        sleep(1);
        $this->assertFalse(self::$mkt->isOrderOpen($res));
        $oe = self::$mkt->getOrderExecutions($res);
        $this->assertTrue(count($oe) > 1);
    }

    public function testTransactions()
    {
        $this->assertTrue(self::$mkt instanceof Kraken);
        $transactions = self::$mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Kraken", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertTrue(self::$mkt->isOrderAccepted($response));

        //give time to put order on book
        sleep(1);
        $this->assertTrue(self::$mkt->isOrderOpen($response));

        $cres = self::$mkt->cancel(self::$mkt->getOrderID($response));
        $this->assertTrue($cres['count'] == 1);

        $this->assertFalse(self::$mkt->isOrderOpen($response));
    }
}
 
