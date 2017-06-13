<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/1/2016
 * Time: 4:10 AM
 */

namespace CryptoMarket\Account\Tests;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use CryptoMarket\Account\EthereumAccount;
use CryptoMarket\Account\EthereumClassicAccount;

class EthereumAccountTest extends TestCase
{
    public function testBalances()
    {
        $ea = new EthereumAccount('0xf978b025b64233555cc3c19ada7f4199c9348bf7');
        $bal = $ea->balances();

        $this->assertNotNull($bal);

        $eca = new EthereumClassicAccount('0xf978b025b64233555cc3c19ada7f4199c9348bf7');
        $bal2 = $eca->balances();

        $this->assertNotNull($bal);
    }
}
