<?php

namespace CryptoMarket\Record;

class Currency
{
    // Fiat currencies
    const USD = 'USD';
    const EUR = 'EUR';
    const GBP = 'GBP';
    const CNY = 'CNY';
    const RUR = 'RUR';

    const FIAT_CURRENCIES = array(Currency::USD,
        Currency::EUR,
        Currency::GBP,
        Currency::CNY,
        Currency::RUR);

    // Crypto-currencies
    const BTC = 'BTC';
    const FTC = 'FTC';
    const LTC = 'LTC';
    const DRK = 'DRK';
    const NXT = 'NXT';
    const XMR = 'XMR';
    const XCP = 'XCP';
    const XRP = 'XRP';
    const ETH = 'ETH';
    const DAO = 'DAO';
    const ETC = 'ETC';

    public static function isFiat($currency)
    {
        return in_array($currency, Currency::FIAT_CURRENCIES);
    }

    public static function getPrecision($currency)
    {
        if (Currency::isFiat($currency)) {
            return 2;
        } else {
            return 8;
        }
    }

    public static function GetMinimumValue($currency, $currencyPrecision = INF)
    {
        $p = min(self::getPrecision($currency), $currencyPrecision);
        return pow(10, -$p);
    }

    public static function FloorValue($value, $currency, $currencyPrecision = INF)
    {
        $p = min(self::getPrecision($currency), $currencyPrecision);

        $mul = pow(10, $p);
        return bcdiv(floor(bcmul($value, $mul, $p)), $mul, $p); //bc math lib avoids floating point weirdness
    }

    public static function RoundValue($value, $currency, $roundMode = PHP_ROUND_HALF_UP)
    {
        $p =  self::getPrecision($currency);

        return round($value, $p, $roundMode);
    }
}

