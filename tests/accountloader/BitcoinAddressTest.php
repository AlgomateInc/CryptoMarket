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
        $ba = new BitcoinAddress('1CK6KHY6MHgYvmRQ4PAafKYDrg1ejbH1cE,18cBEMRxXHqzWWCxZNtU91F5sbUNKhL5PX');
        $bal = $ba->balances();
        $this->assertNotNull($bal);
    }
}
