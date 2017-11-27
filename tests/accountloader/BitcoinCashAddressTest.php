<?php

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\Account\BitcoinCashAddress;

class BitcoinCashAddressTest extends TestCase
{
    public function testBalances()
    {
        $ba = new BitcoinCashAddress('17Wk4GPKw9nZ9PbspzaxN3fv1L2m9NA9dg');
        $bal = $ba->balances();
        $this->assertNotNull($bal);
    }
}
