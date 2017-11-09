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
    protected $mkt;

    public function __construct()
    {
        parent::__construct();

        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Gemini));
        $this->mkt = $exchanges[ExchangeName::Gemini];
        $this->mkt->init();
    }

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
    }

    public function testPrecisions()
    {
        $this->assertTrue($this->mkt instanceof Gemini);
        $this->assertEquals(2, $this->mkt->quotePrecision(CurrencyPair::BTCUSD, 1));
        $this->assertEquals(2, $this->mkt->quotePrecision(CurrencyPair::ETHUSD, 1));
        $this->assertEquals(5, $this->mkt->quotePrecision(CurrencyPair::ETHBTC, 1));
    }

    public function testBalances()
    {
        if ($this->mkt instanceof Gemini)
        {
            $ret = $this->mkt->balances();
            $this->assertNotEmpty($ret);
        }
    }

    public function testFees()
    {
        $this->assertTrue($this->mkt instanceof Gemini);
        $this->assertEquals(0.25, $this->mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 1000.0));
        $this->assertEquals(0.15, $this->mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 10000.0));
        $this->assertEquals(0.25, $this->mkt->tradingFee(CurrencyPair::ETHUSD, TradingRole::Taker, 10.0));
        $this->assertEquals(0.15, $this->mkt->tradingFee(CurrencyPair::ETHUSD, TradingRole::Taker, 200000.0));
    }

    public function testUserFees()
    {
        $this->assertTrue($this->mkt instanceof Gemini);
        $this->assertEquals(0.25, $this->mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker));
        $this->assertEquals(0.25, $this->mkt->currentTradingFee(CurrencyPair::ETHUSD, TradingRole::Maker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue($this->mkt instanceof Gemini);
        $schedule = $this->mkt->currentFeeSchedule();
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
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
        $this->assertTrue($this->mkt instanceof Gemini);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $quotePrecision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);
            $minOrder = $this->mkt->minimumOrderSize($pair, $price);

            $ret = $this->mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
            sleep(1);
        }
    }

    public function testBasePrecision()
    {
        $this->assertTrue($this->mkt instanceof Gemini);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $quotePrecision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);

            $minOrder = $this->mkt->minimumOrderSize($pair, $price);
            $basePrecision = $this->mkt->basePrecision($pair, $ticker->bid);
            $minOrder += bcpow(10, -1 * $basePrecision, $basePrecision);

            $ret = $this->mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
            sleep(1);
        }
    }

    public function testBuyOrderSubmission()
    {
        if ($this->mkt instanceof Gemini)
        {
            $response = $this->mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if ($this->mkt instanceof Gemini)
        {
            $response = $this->mkt->sell(CurrencyPair::BTCUSD, 0.01, 10000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if ($this->mkt instanceof Gemini)
        {
            $res = $this->mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if ($this->mkt instanceof Gemini)
        {
            $res = $this->mkt->trades(CurrencyPair::BTCUSD, time()-60);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));
        $this->assertTrue($this->mkt->isOrderOpen($response));

        $this->assertNotNull($this->mkt->cancel($response['order_id']));
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }
}
 
