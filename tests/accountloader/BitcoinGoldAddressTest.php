<?php

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\Account\BitcoinGoldAddress;

class BitcoinGoldAddressTest extends TestCase
{
    public function testBalances()
    {
        $ba = new BitcoinGoldAddress('GVQiajM9TTSNVATL3JEGLG9s48TWHTJg8S');
        $bal = $ba->balances();
        var_dump($bal);
        $this->assertNotNull($bal);
    }
}
