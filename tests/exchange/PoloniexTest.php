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
use CryptoMarket\Exchange\Poloniex;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class PoloniexTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        date_default_timezone_set('UTC');
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Poloniex));
        self::$mkt = $exchanges[ExchangeName::Poloniex];
        self::$mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_ALL);
    }

    public function testSupportedPairs()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $this->assertTrue(self::$mkt->supports(CurrencyPair::BTCUSD));
        $this->assertTrue(self::$mkt->supports(CurrencyPair::ETHUSD));
        $this->assertTrue(self::$mkt->supports(CurrencyPair::ETHBTC));
        $this->assertTrue(self::$mkt->supports('LTCXMR'));
    }

    public function testGetAllMarketData()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $ret = self::$mkt->tickers();
        foreach ($ret as $ticker) {
            $this->assertTrue(self::$mkt->supports($ticker->currencyPair));
            $this->assertNotNull($ticker->bid);
            $this->assertNotNull($ticker->ask);
            $this->assertNotNull($ticker->last);
            $this->assertNotNull($ticker->volume);
        }
    }

    public function testMinOrderSize()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $this->assertEquals(0.000001, self::$mkt->minimumOrderSize(CurrencyPair::BTCUSD, 1202));
        $this->assertGreaterThan(0.000001, self::$mkt->minimumOrderSize(CurrencyPair::BTCUSD, 0.01));
    }

    public function testMinOrders()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $this->markTestSkipped();
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
        $this->assertTrue(self::$mkt instanceof Poloniex);
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
            sleep(1);
        }
    }

    public function testBalances()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $ret = self::$mkt->balances();
        $this->assertNotEmpty($ret);
    }

    public function testFees()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $usdMakerFee = self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Maker);
        $eurMakerFee = self::$mkt->currentTradingFee(CurrencyPair::BTCEUR, TradingRole::Maker);
        $this->assertEquals($usdMakerFee, $eurMakerFee);
        $this->assertEquals(0.15, $eurMakerFee);
        $usdTakerFee = self::$mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker);
        $eurTakerFee = self::$mkt->currentTradingFee(CurrencyPair::BTCEUR, TradingRole::Taker);
        $this->assertEquals($usdTakerFee, $eurTakerFee);
        $this->assertEquals(0.25, $eurTakerFee);

        foreach (self::$mkt->supportedCurrencyPairs() as $pair) {
            $quote = CurrencyPair::Quote($pair);
            if ($quote == Currency::BTC) {
                $this->assertEquals(0.1, self::$mkt->tradingFee($pair, TradingRole::Taker, 3.0e4));
                $this->assertEquals(0.0, self::$mkt->tradingFee($pair, TradingRole::Maker, 3.0e4));
            }
        }
    }

    public function testFeeSchedule()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
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
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $response = self::$mkt->buy(CurrencyPair::XCPBTC, 1, 0.0001);
        $this->checkAndCancelOrder($response);
    }

    public function testSellOrderSubmission()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $response = self::$mkt->sell(CurrencyPair::XCPBTC, 1, 1000);
        $this->checkAndCancelOrder($response);
    }

    public function testOrderExecutions()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $response = self::$mkt->buy(CurrencyPair::XCPBTC, 1, 1);
        $oe = self::$mkt->getOrderExecutions($response);

        $this->assertNotNull($oe);
        $this->assertTrue(count($oe) > 0);
    }

    public function testMyTrades()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $res = self::$mkt->tradeHistory(50);
        $this->assertNotNull($res);
    }

    public function testPublicTrades()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $res = self::$mkt->trades(CurrencyPair::XCPBTC, time()-600);
        $this->assertNotNull($res);
    }

    public function testTransactions()
    {
        $this->assertTrue(self::$mkt instanceof Poloniex);
        $transactions = self::$mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Poloniex", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue(self::$mkt->isOrderAccepted($response));
        $this->assertTrue(self::$mkt->isOrderOpen($response));

        $this->assertNotNull(self::$mkt->cancel(self::$mkt->getOrderID($response)));
        sleep(1);
        $this->assertFalse(self::$mkt->isOrderOpen($response));
    }
}
 
