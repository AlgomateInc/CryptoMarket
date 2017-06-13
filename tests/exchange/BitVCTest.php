<?php

/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 1:27 PM
 */

namespace CryptoMarket\Exchange\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\AccountLoader\ConfigAccountLoader;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\BitVC;

use CryptoMarket\Record\CurrencyPair;

class BitVCTest extends TestCase
{
    protected $mkt;

    public function setUp()
    {
        error_reporting(error_reporting() ^ E_NOTICE);

        $cal = new ConfigAccountLoader();
        $exchanges = $cal->getAccounts(array(ExchangeName::BitVC));
        $this->mkt = $exchanges[ExchangeName::BitVC];
    }

    public function testBalances()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->balances();

            $this->assertNotEmpty($ret);
        }
    }

    public function testTicker()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->ticker(CurrencyPair::BTCCNY);

            $this->assertNotEmpty($ret);
        }
    }

    public function testDepth()
    {
        if($this->mkt instanceof BitVC)
        {
            $ret = $this->mkt->depth(CurrencyPair::BTCCNY);

            $this->assertNotEmpty($ret);
        }
    }
}
 
