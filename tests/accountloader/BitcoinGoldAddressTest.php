<?php

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\Account\BitcoinGoldAddress;

class BitcoinGoldAddressTest extends TestCase
{
    public function testBalances()
    {
        $ba = new BitcoinGoldAddress('GQ6Btf3KmRz4VoMsEZi7WBdCYuY1XeXTrY,GVQiajM9TTSNVATL3JEGLG9s48TWHTJg8S');
        $bal = $ba->balances();
        $this->assertNotNull($bal);
    }
}
