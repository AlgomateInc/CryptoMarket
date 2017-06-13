<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 9/24/2014
 * Time: 9:43 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Btce;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;

class BtceTest extends TestCase
{
    protected $mkt;
    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(ExchangeName::Btce));
        $this->mkt = $exchanges[ExchangeName::Btce];
        $this->mkt->init();
    }

    public function testBalances()
    {
        $this->assertTrue($this->mkt instanceof Btce);
        $currs = $this->mkt->supportedCurrencies();
        $bal = $this->mkt->balances();
        foreach ($bal as $pair=>$amt) {
            $this->assertTrue(in_array($pair, $currs));
            $this->assertTrue(is_int($amt) || is_float($amt));
        }
    }

    public function testFees()
    {
        $this->assertTrue($this->mkt instanceof Btce);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $this->assertEquals(0.2, $this->mkt->currentTradingFee($pair, TradingRole::Taker));
            $this->assertEquals(0.2, $this->mkt->currentTradingFee($pair, TradingRole::Maker));
            sleep(1);
        }
    }

    public function testFeeSchedule()
    {
        $this->assertTrue($this->mkt instanceof Btce);
        $schedule = $this->mkt->currentFeeSchedule();
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $taker = $schedule->getFee($pair, TradingRole::Taker);
            $this->assertNotNull($taker);
            $maker = $schedule->getFee($pair, TradingRole::Maker);
            $this->assertNotNull($maker);
            sleep(1);
        }
    }

    public function testPrecisions()
    {
        $this->assertTrue($this->mkt instanceof Btce);
        foreach ($this->mkt->supportedCurrencyPairs() as $pair) {
            $ticker = $this->mkt->ticker($pair);
            $precision = $this->mkt->quotePrecision($pair, $ticker->bid);
            $this->assertEquals($ticker->bid, round($ticker->bid, $precision));
            $this->assertEquals($ticker->ask, round($ticker->ask, $precision));
            $this->assertEquals($ticker->last, round($ticker->last, $precision));
            sleep(1);
        }
    }

    public function testMinOrders()
    {
        $this->assertTrue($this->mkt instanceof Btce);
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
        $this->assertTrue($this->mkt instanceof Btce);
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

    public function testBTCEUROrder()
    {
        $res = $this->mkt->sell(CurrencyPair::BTCEUR, 0.01, 3000.12345);
        $this->assertTrue($this->mkt->isOrderAccepted($res));
        sleep(1);
        $cres = $this->mkt->cancel($res['return']['order_id']);
    }

    public function testOrderSubmitAndCancel()
    {
        if ($this->mkt instanceof Btce)
        {
            $res = $this->mkt->buy(CurrencyPair::BTCUSD, 1, 1);
            $this->assertTrue($this->mkt->isOrderAccepted($res));
            sleep(1);
            $cres = $this->mkt->cancel($res['return']['order_id']);
        }
    }

    public function testTradeHistory()
    {
        if ($this->mkt instanceof Btce)
        {
            $res = $this->mkt->tradeHistory(5);

            $this->assertTrue(is_array($res));
            $this->assertCount(5, $res);
        }
    }

    private function checkAndCancelOrder($response)
    {
        $this->assertNotNull($response);
        $this->assertTrue($this->mkt->isOrderAccepted($response));

        //give time to put order on book
        sleep(1);

        $cres = $this->mkt->cancel($response['return']['order_id']);
    }
}
 
