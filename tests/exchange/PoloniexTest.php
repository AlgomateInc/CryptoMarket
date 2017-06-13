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

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Poloniex;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;

class PoloniexTest extends TestCase
{
    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);
        date_default_timezone_set('UTC');

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(ExchangeName::Poloniex));
        $this->mkt = $exchanges[ExchangeName::Poloniex];
    }

    public function testMinOrderSize()
    {
        $this->assertTrue($this->mkt instanceof Poloniex);
        $this->assertEquals(0.000001, $this->mkt->minimumOrderSize(CurrencyPair::BTCUSD, 1202));
        $this->assertGreaterThan(0.000001, $this->mkt->minimumOrderSize(CurrencyPair::BTCUSD, 0.01));
    }

    public function testMinOrders()
    {
        $this->assertTrue($this->mkt instanceof Poloniex);
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
        $this->assertTrue($this->mkt instanceof Poloniex);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $quotePrecision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $price = round($ticker->bid * 0.9, $quotePrecision);

            $minOrder = $this->mkt->minimumOrderSize($pair, $price);
            $basePrecision = $this->mkt->basePrecision($pair, $ticker->bid);
            $minOrder += bcpow(10, -1 * $basePrecision, $basePrecision);

            $ret = $this->mkt->buy($pair, $minOrder, $price);
            $this->checkAndCancelOrder($ret);
        }
    }

    public function testBalances()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $ret = $this->mkt->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testFees()
    {
        $this->assertTrue($this->mkt instanceof Poloniex);
        $usdMakerFee = $this->mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Maker);
        $eurMakerFee = $this->mkt->currentTradingFee(CurrencyPair::BTCEUR, TradingRole::Maker);
        $this->assertEquals($usdMakerFee, $eurMakerFee);
        $this->assertEquals(0.15, $eurMakerFee);
        $usdTakerFee = $this->mkt->currentTradingFee(CurrencyPair::BTCUSD, TradingRole::Taker);
        $eurTakerFee = $this->mkt->currentTradingFee(CurrencyPair::BTCEUR, TradingRole::Taker);
        $this->assertEquals($usdTakerFee, $eurTakerFee);
        $this->assertEquals(0.25, $eurTakerFee);

        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $quote = CurrencyPair::Quote($pair);
            if ($quote == Currency::BTC) {
                $this->assertEquals(0.1, $this->mkt->tradingFee($pair, TradingRole::Taker, 3.0e4));
                $this->assertEquals(0.0, $this->mkt->tradingFee($pair, TradingRole::Maker, 3.0e4));
            }
        }
    }

    public function testFeeSchedule()
    {
        $this->assertTrue($this->mkt instanceof Poloniex);
        $schedule = $this->mkt->currentFeeSchedule();
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
        }
    }

    public function testBuyOrderSubmission()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $response = $this->mkt->buy(CurrencyPair::XCPBTC, 1, 0.0001);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testSellOrderSubmission()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $response = $this->mkt->sell(CurrencyPair::XCPBTC, 1, 1000);
            $this->checkAndCancelOrder($response);
        }
    }

    public function testOrderExecutions()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $response = $this->mkt->buy(CurrencyPair::XCPBTC, 1, 1);
            $oe = $this->mkt->getOrderExecutions($response);

            $this->assertNotNull($oe);
            $this->assertTrue(count($oe) > 0);
        }
    }

    public function testMyTrades()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $res = $this->mkt->tradeHistory(50);
            $this->assertNotNull($res);
        }
    }

    public function testPublicTrades()
    {
        if ($this->mkt instanceof Poloniex)
        {
            $res = $this->mkt->trades(CurrencyPair::XCPBTC, time()-600);
            $this->assertNotNull($res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        if (!$this->mkt instanceof Poloniex)
            return;

        $this->assertNotNull($response);

        $this->assertTrue($this->mkt->isOrderAccepted($response));
        $this->assertTrue($this->mkt->isOrderOpen($response));

        $this->assertNotNull($this->mkt->cancel($this->mkt->getOrderID($response)));
        $this->assertFalse($this->mkt->isOrderOpen($response));
    }
}
 
