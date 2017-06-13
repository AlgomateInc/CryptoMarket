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

class BitfinexTest extends TestCase
{
    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Bitfinex));
        $this->mkt = $exchanges[ExchangeName::Bitfinex];
        $this->mkt->init();
    }

    public function testPrecision()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        $pair = CurrencyPair::BTCUSD;
        $this->assertEquals(4, $this->mkt->quotePrecision($pair, 1.0));
        $this->assertEquals(5, $this->mkt->quotePrecision($pair, 0.1));
        $this->assertEquals(1, $this->mkt->quotePrecision($pair, 1000.0));
        $this->assertEquals(-2, $this->mkt->quotePrecision($pair, 1000000.0));
        sleep(1);
    }

    public function testBalances()
    {
        $ret = $this->mkt->balances();

        $this->assertNotEmpty($ret);
        sleep(1);
    }

    public function testMinOrders()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
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
        $this->assertTrue($this->mkt instanceof Bitfinex);
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

    public function testFees()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        $this->assertEquals('0.2', $this->mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Taker, 0.0));
        sleep(1);
        $this->assertEquals('0.08', $this->mkt->tradingFee(CurrencyPair::BTCUSD, TradingRole::Maker, 5.0e5));
        sleep(1);
        $this->assertEquals('0.1', $this->mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Maker));
        sleep(1);
        $this->assertEquals('0.2', $this->mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker));
    }

    public function testFeeSchedule()
    {
        $this->assertTrue($this->mkt instanceof Bitfinex);
        $schedule = $this->mkt->currentFeeSchedule();
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
            sleep(1);
        }
    }

    public function testBuyOrderSubmission()
    {
        if ($this->mkt instanceof Bitfinex)
        {
            $response = $this->mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if ($this->mkt instanceof Bitfinex)
        {
            $response = $this->mkt->sell(CurrencyPair::BTCUSD, 1, 10000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testMyTrades()
    {
        if ($this->mkt instanceof Bitfinex)
        {
            $res = $this->mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if ($this->mkt instanceof Bitfinex)
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
        sleep(1);
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }
}
 
