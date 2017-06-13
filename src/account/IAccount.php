<?php

namespace CryptoMarket\Account;

interface IAccount {
    /**
     * @return String Simple name for account or account provider
     */
    public function Name();

    /**
     * @return array An associative array mapping Currency to numerical balance
     */
    public function balances();
    public function transactions();
}

