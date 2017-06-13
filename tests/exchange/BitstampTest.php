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

use CryptoMarketTest\AccountConfigData;

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
    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader(AccountsConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Bitstamp));
        $this->mkt = $exchanges[ExchangeName::Bitstamp];
        $this->mkt->init();
    }

    public function testDepth()
    {
        $depth = $this->mkt->depth(CurrencyPair::XRPEUR);
        $this->assertNotEmpty($depth);
    }

    public function testTicker()
    {
        $ticker = $this->mkt->ticker(CurrencyPair::XRPEUR);
        $this->assertNotEmpty($ticker);
        $this->assertTrue($ticker instanceof Ticker);
    }

    public function testBalances()
    {
        $balances = $this->mkt->balances();
        $currs = $this->mkt->supportedCurrencies();
        foreach ($currs as $curr) {
            $this->assertArrayHasKey($curr, $balances);
        }
    }

    public function testFees()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $this->assertEquals(0.25, $this->mkt->currentTradingFee($pair, TradingRole::Taker));
            $this->assertEquals(0.13, $this->mkt->tradingFee($pair, TradingRole::Taker, 1.1e6));
            sleep(1);
        }
    }

    public function testFeeSchedule()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $schedule = $this->mkt->currentFeeSchedule();
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
            sleep(1);
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
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
        $this->assertTrue($this->mkt instanceof Bitstamp);
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

    public function testBTCUSDOrder()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCUSD, 0.01, 100000);
        $this->checkAndCancelOrder($response);
    }

    public function testBTCEUROrder()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->checkAndCancelOrder($response);
    }

    public function testActiveOrders()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 100000);
        $this->assertNotEmpty($this->mkt->activeOrders());
        $this->assertTrue($this->mkt->isOrderOpen($response));
        $this->checkAndCancelOrder($response);
    }

    public function testOrderExecutions()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $response = $this->mkt->buy(CurrencyPair::BTCEUR, 0.01, 100000);
        sleep(1);
        $exs = $this->mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
        $response = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 1);
        sleep(1);
        $exs = $this->mkt->getOrderExecutions($response);
        $this->assertNotEmpty($exs);
    }

    public function testTransactions()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $transactions = $this->mkt->transactions();
        $this->assertNotEmpty($transactions);
        foreach ($transactions as $trans) {
            $this->assertEquals("Bitstamp", $trans->exchange);
            $this->assertTrue($trans instanceof Transaction);
            $this->assertTrue($trans->isValid());
        }
    }

    public function testTradeHistory()
    {
        $this->assertTrue($this->mkt instanceof Bitstamp);
        $history = $this->mkt->tradeHistory();
        foreach ($history as $trade) {
            $this->assertEquals("Bitstamp", $trade->exchange);
            $this->assertTrue($trade instanceof Trade);
            $this->assertTrue($trade->isValid());
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);

        sleep(1);
        
        $this->assertTrue($this->mkt->isOrderAccepted($response));

        //give time to bitstamp to put order on book
        sleep(1);

        $this->assertTrue($this->mkt->cancel($response['id']));
    }
}
 
