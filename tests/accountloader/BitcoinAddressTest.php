<?php

/**
 * User: jon
 * Date: 5/9/2017
 * Time: 4:10 AM
 */

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\Account\BitcoinAddress;

class BitcoinAddressTest extends TestCase
{
    public function testBalances()
    {
        $ba = new BitcoinAddress('1CBhgtax9Q7aTRJzpRQc8qEx8kcRxncwvi');
        $bal = $ba->balances();
        $this->assertNotNull($bal);
    }
}
