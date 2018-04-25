<?php
/**
 * User: jon
 * Date: 4/24/2018
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarketTest\ConfigData;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Binance;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\TradingRole;
use CryptoMarket\Record\Transaction;

class BinanceTest extends TestCase
{
    protected static $mkt;
    public static function setUpBeforeClass()
    {
        $cal = new ConfigAccountLoader(ConfigData::ACCOUNTS_CONFIG);
        $exchanges = $cal->getAccounts(array(ExchangeName::Binance));
        self::$mkt = $exchanges[ExchangeName::Binance];
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
            $this->assertNotNull($ticker->volume);
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
        $pair = 'MANA/BTC';
        $t = self::$mkt->ticker($pair);
        $price = $t->bid * 0.8;
        $res = self::$mkt->buy($pair, 0.0011 / $price, $price);
        $this->assertNotNull($res);
        sleep(1);
        $this->assertTrue(self::$mkt->isOrderOpen($res));
        $res = self::$mkt->cancel(self::$mkt->getOrderID($res));
    }

    public function testExecutions()
    {
        $resp = [ 'symbol' => 'MANABTC', 'orderId' => 8287200 ];
        $execs = self::$mkt->getOrderExecutions($resp);
        $this->assertNotEmpty($execs);
    }

    public function testTradeHistory()
    {
        $trades = self::$mkt->tradeHistory(20);
        $this->assertNotEmpty($trades);
    }

    public function testTransactions()
    {
    }
}
