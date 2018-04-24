<?php
/**
 * User: jon
 * Date: 4/19/2018
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Bittrex;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class BittrexTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Bittrex));
        self::$mkt = $exchanges[ExchangeName::Bittrex];
        self::$mkt->init();
    }

    public function testSupportedPairs()
    {
        $known_pairs = array("BTCUSD","ETHUSD","ETHBTC","LTCUSD","LTCBTC");
        foreach ($known_pairs as $pair) {
            $this->assertTrue(self::$mkt->supports($pair));
        }
    }

    public function testTicker()
    {
        $known_pairs = array("BTCUSD","ETHUSD","ETHBTC","LTCUSD","LTCBTC");
        foreach ($known_pairs as $pair) {
            $ticker = self::$mkt->ticker($pair);
            $this->assertNotNull($ticker->bid);
            $this->assertNotNull($ticker->ask);
            $this->assertNotNull($ticker->last);
            $this->assertNull($ticker->volume);
        }
    }

    public function testTickers()
    {
        $tickers = self::$mkt->tickers();
        foreach ($tickers as $ticker) {
            $this->assertTrue(self::$mkt->supports($ticker->currencyPair));
            $this->assertNotNull($ticker->bid);
            $this->assertNotNull($ticker->ask);
            $this->assertNotNull($ticker->last);
            $this->assertNotNull($ticker->volume);
        }
    }

    public function testDepth()
    {
        $depth = self::$mkt->depth(CurrencyPair::BTCUSD);
        $this->assertNotNull($depth);
    }

    public function testMinOrderSize()
    {
        $min_size = self::$mkt->minimumOrderSize(CurrencyPair::BTCUSD, 0.0);
        $this->assertNotNull($min_size);
    }

    public function testTrades()
    {
        $half_hour_ago = time() - 1800;
        $trades = self::$mkt->trades(CurrencyPair::BTCUSD, $half_hour_ago);
        $this->assertNotNull($trades);
        foreach ($trades as $trade) {
            $this->assertGreaterThanOrEqual($half_hour_ago, $trade->timestamp->toDateTime()->getTimestamp());
        }
    }

    public function testBalances()
    {
        $bals = self::$mkt->balances();
        $this->assertNotNull($bals);
        $this->assertNotEmpty($bals);
    }

    public function testBuy()
    {
        $pair = 'XRPBTC';
        $t = self::$mkt->ticker($pair);
        $minOrder = 0.0005;
        $price = $t->bid * 0.8;
        $res = self::$mkt->buy($pair, $minOrder / $price, $price);
        $this->assertNotNull($res);
        sleep(1);
        $this->assertTrue(self::$mkt->isOrderOpen($res));
        self::$mkt->cancel(self::$mkt->getOrderID($res));
    }

    public function testSell()
    {
        $pair = 'XRPBTC';
        $t = self::$mkt->ticker($pair);
        $minOrder = 0.0005;
        $price = $t->ask * 1.2;
        $res = self::$mkt->sell($pair, $minOrder / $price, $price); 
        $this->assertNotNull($res);
        sleep(1);
        $this->assertTrue(self::$mkt->isOrderOpen($res));
        self::$mkt->cancel(self::$mkt->getOrderID($res));
    }

    public function testExecutions()
    {
        $resp = [ 'uuid' => '03611f30-4259-410e-84b6-ea0283d251df' ];
        $execs = self::$mkt->getOrderExecutions($resp);
        var_dump($execs);
        $this->assertNotEmpty($execs);
    }

    public function testTradeHistory()
    {
        $trades = self::$mkt->tradeHistory(20);
        $this->assertNotEmpty($trades);
    }

    public function testTransactions()
    {
        $trans = self::$mkt->transactions();
        $this->assertNotEmpty($trans);
    }
}
