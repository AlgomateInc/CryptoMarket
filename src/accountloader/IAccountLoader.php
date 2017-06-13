<?php

namespace CryptoMarket\AccountLoader;

interface IAccountLoader {
    function getAccounts(array $mktFilter = null);
    function getConfig();
} 

