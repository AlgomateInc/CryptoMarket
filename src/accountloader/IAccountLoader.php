<?php

namespace CryptoMarket\AccountLoader;

interface IAccountLoader {
    function getAccounts(array $mktFilter = null, $privateKey = null);
    function getConfig($privateKey = null);
} 

