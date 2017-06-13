<?php

namespace CryptoMarketTest;

class ConfigDataExample
{
    const MONGODB_URI = 'mongodb://localhost';
    const MONGODB_DBNAME = 'coindata';

    const ACCOUNTS_CONFIG = array(
        'Btce' => array(
            'key' => '',
            'secret' => ''
        ),
        'Bitfinex'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Bitstamp'=> array(
            'key' => '',
            'secret' => '',
            'custid' => ''
        ),
        'BitVC'=> array(
            'key' => '',
            'secret' => '',
        ),
//      'JPMChase' => array(
//          'name' => 'imapmailbox',
//          'username' => 'username',
//          'password' => 'password'
//      ),
        'Gdax'=> array(
            'key' => '',
            'secret' => '',
            'passphrase' =>''
        ),
        'Gemini'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Kraken'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Poloniex'=> array(
            'key' => '',
            'secret' => ''
        ),
        'Yunbi'=> array(
            'key' => '',
            'secret' => '',
        ),
    );
}

