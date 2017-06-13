# CryptoMarket
PHP package defining a unified interface for interacting with cryptocurrency exchanges

# Installing

## Composer

Composer is required to fetch and use the dependencies.  After cloning the repo,
run:

    $ composer install

## ConfigData.php initial setup

The CryptoMarket package is configured using the "ConfigData" class, to 
be defined in the top-level "tests/" directory.  "ConfigDataExample.php" 
is provided as a template, which only allows access to public exchange APIs. To
setup the basic ConfigData, run:

    $ cp tests/ConfigDataExample.php tests/ConfigData.php
    $ sed -i "s#ConfigDataExample#ConfigData#" tests/ConfigData.php
    $ composer dumpautoload

Note: For saftey, ConfigData.php is in .gitignore, so your API keys will 
not be accidentally checked in.

## Test setup

From there, run the smoke tests to make sure everything is setup:

    $ ./vendor/bin/phpunit tests/SmokeTest.php

# Exchange API Keys

In order to use private exchange APIs such as "buy" and "sell", CryptoMarket 
needs API keys for each exchange.

## Using Exchange classes in your code

To create Exchange instances with API key info, first create an instance of 
IAccountLoader and then use its "getAccounts" function, providing an array of 
"Record::ExchangeName".  The exchanges currently supported are:

  * Bitfinex
  * Bitstamp
  * Btce
  * GDAX
  * Gemini
  * Kraken
  * Poloniex
  * Yunbi

## IAccountLoader Implementations

Currently, there are two implementations of IAccountLoader: 

  * ConfigAccountLoader -- stored in plaintext file
  * MongoAccountLoader -- stored in mongodb collection

## Using ConfigAccountLoader

  * Add keys, secrets, and additional information to the ACCOUNTS_CONFIG section
  in tests/ConfigData.php for desired exchanges
  * Instantiate ConfigAccountLoader with ConfigData::ACCOUNTS_CONFIG
  * Call "getAccounts"

## Using MongoAccountLoader

  * Add MONGODB_URI and MONGODB_NAME to tests/ConfigData.php
  * Add entries to the "servers" collection using the following document format:
  {
    'ServerName': 'xxxxxxxx', // any user-defined name, used to construct MongoAccountLoader
    'ExchangeSettings': [
      {
        'Name': 'SomeExchangeName', // see Record::ExchangeName for supported names
        'Settings': { // all key->value pairs to be passed into Exchange, e.g:
          'key': 'xxxxxxxxxxx',
          'secret': 'xxxxxxxxxxxxx',
        }
      },
      // all other exchanges follow
    ]
  }
  * Instantiate MongoAccountLoader with the following:
    - ConfigData::MONGODB_URI
    - ConfigData::MONGODB_DBNAME
    - ConfigData::ACCOUNTS_CONFIG
    - "ServerName" specified in the previous step
  * Call "getAccounts"

# Running tests

All tests already include /vendor/autoload.php, so tests can be run using phpunit
found in the vendor directory, e.g.:

    $ ./vendor/bin/phpunit tests/exchange/BitfinexTest.php
    
# Code Contributions

Please follow the guidelines by: http://contribution-guide-org.readthedocs.io/

