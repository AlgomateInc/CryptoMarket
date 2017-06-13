<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Exchange\IExchange;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;

abstract class BaseExchange implements IExchange {

    public function supports($currencyPair){
        $findBase = CurrencyPair::Base($currencyPair);
        $findQuote = CurrencyPair::Quote($currencyPair);

        foreach ($this->supportedCurrencyPairs() as $pair)
        {
            $base = CurrencyPair::Base($pair);
            $quote = CurrencyPair::Quote($pair);

            if ($base == $findBase && $quote == $findQuote)
                return true;
        }

        return false;
    }

    public function supportedCurrencies(){

        $currList = array();

        foreach($this->supportedCurrencyPairs() as $pair)
        {
            $base = CurrencyPair::Base($pair);
            $quote = CurrencyPair::Quote($pair);

            if(!in_array($base, $currList))
                $currList[] = $base;

            if(!in_array($quote, $currList))
                $currList[] = $quote;
        }

        return $currList;
    }

    public function tickers()
    {
        $ret = array();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $ret[] = $this->ticker($pair);
        }
        return $ret;
    }

    public function basePrecision($pair, $pairRate)
    {
        return Currency::getPrecision(CurrencyPair::Base($pair));
    }

    public function quotePrecision($pair, $pairRate)
    {
        return Currency::getPrecision(CurrencyPair::Quote($pair));
    }
} 
