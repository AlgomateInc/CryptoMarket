<?php

namespace CryptoMarket\Record;

class CurrencyPair
{
    // Crypto currency pairs
    const BTCUSD = 'BTCUSD';
    const BTCEUR = 'BTCEUR';
    const XRPUSD = 'XRPUSD';
    const XRPEUR = 'XRPEUR';
    const XRPBTC = 'XRPBTC';
    const FTCBTC = 'FTCBTC';
    const LTCBTC = 'LTCBTC';
    const LTCUSD = 'LTCUSD';
    const DRKUSD = 'DRKUSD';
    const NXTBTC = 'NXTBTC';
    const DRKBTC = 'DRKBTC';
    const XMRBTC = 'XMRBTC';
    const XCPBTC = 'XCPBTC';
    const MAIDBTC = 'MAID/BTC';
    const ETHBTC = 'ETHBTC';
    const ETHUSD = 'ETHUSD';
    const DAOETH = 'DAOETH';
    const BTCCNY = 'BTCCNY';
    const ETHCNY = 'ETHCNY';
    const DGDCNY = 'DGDCNY';
    const PLSCNY = 'PLSCNY';
    const BTSCNY = 'BTSCNY';
    const BITCNYCNY = 'BITCNY/CNY';
    const DCSCNY = 'DCSCNY';
    const SCCNY = 'SC/CNY';
    const ETCCNY = 'ETCCNY';
    const FSTCNY = '1SŦCNY'; // ! NB: Variables can't start with numbers
    const REPCNY = 'REPCNY';
    const ANSCNY = 'ANSCNY';
    const ZECCNY = 'ZECCNY';
    const ZMCCNY = 'ZMCCNY';
    const GNTCNY = 'GNTCNY';

    // Fiat currency pairs
    const EURUSD = 'EURUSD';

    public static function Base($strPair){
        $parts = explode('/', $strPair);
        if (count($parts) == 2 && mb_strlen($parts[0]) >= 2 && mb_strlen($parts[1]) >= 2)
            return $parts[0];

        if (mb_strlen($strPair) == 6)
            return mb_substr($strPair, 0, 3);

        throw new \Exception('Unsupported currency pair string');
    }

    public static function Quote($strPair){
        $parts = explode('/', $strPair);
        if (count($parts) == 2 && mb_strlen($parts[0]) >= 2 && mb_strlen($parts[1]) >= 2)
            return $parts[1];

        if (mb_strlen($strPair) == 6)
            return mb_substr($strPair, 3, 3);

        throw new \Exception('Unsupported currency pair string');
    }

    public static function MakePair($base, $quote)
    {
        if(mb_strlen($base) != 3 || mb_strlen($quote) != 3)
            return $base . '/' . $quote;

        return $base . $quote;
    }
}

