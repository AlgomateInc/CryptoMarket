<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 10/5/2015
 * Time: 10:46 PM
 */

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;

use CryptoMarket\Exchange\Bitfinex;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\FeeScheduleItem;
use CryptoMarket\Record\FeeScheduleList;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\TradingRole;

class Gemini extends Bitfinex
{
    public function Name()
    {
        return "Gemini";
    }

    protected $basePrecisions = array(); //assoc array pair->minIncrement

    function init()
    {
        $this->feeSchedule = new FeeSchedule();

        // From https://gemini.com/fee-schedule/
        $btcFeeSchedule = new FeeScheduleList();
        $btcFeeSchedule->push(new FeeScheduleItem(0.0, 1.0e3, 0.25, 0.25));
        $btcFeeSchedule->push(new FeeScheduleItem(1.0e3, 2.0e3, 0.25, 0.20));
        $btcFeeSchedule->push(new FeeScheduleItem(2.0e3, 3.0e3, 0.25, 0.15));
        $btcFeeSchedule->push(new FeeScheduleItem(3.0e3, 5.0e3, 0.25, 0.10));
        $btcFeeSchedule->push(new FeeScheduleItem(5.0e3, 1.0e4, 0.25, 0.05));
        $btcFeeSchedule->push(new FeeScheduleItem(1.0e4, INF, 0.15, 0.00));

        $ethFeeSchedule = new FeeScheduleList();
        $ethFeeSchedule->push(new FeeScheduleItem(0.0, 2.0e4, 0.25, 0.25));
        $ethFeeSchedule->push(new FeeScheduleItem(2.0e4, 4.0e4, 0.25, 0.20));
        $ethFeeSchedule->push(new FeeScheduleItem(4.0e4, 6.0e4, 0.25, 0.15));
        $ethFeeSchedule->push(new FeeScheduleItem(6.0e4, 1.0e5, 0.25, 0.10));
        $ethFeeSchedule->push(new FeeScheduleItem(1.0e5, 2.0e5, 0.25, 0.05));
        $ethFeeSchedule->push(new FeeScheduleItem(2.0e5, INF, 0.15, 0.00));

        $pairs = CurlHelper::query($this->getApiUrl() . 'symbols');
        foreach($pairs as $geminiPair){
            $pair = mb_strtoupper($geminiPair);
            $this->supportedPairs[] = $pair;

            $base = CurrencyPair::Base($pair);
            if ($base == Currency::BTC) {
                $this->feeSchedule->addPairFees($pair, $btcFeeSchedule);
            } else if ($base == Currency::ETH) {
                $this->feeSchedule->addPairFees($pair, $ethFeeSchedule);
            } else {
                throw new \Exception("Unsupported pair $pair in Gemini, implement proper fee structure");
            }
        }

        // From https://docs.gemini.com/rest-api/#symbols-and-minimums
        $this->minOrderSizes[CurrencyPair::BTCUSD] = 0.00001;
        $this->minOrderSizes[CurrencyPair::ETHUSD] = 0.001;
        $this->minOrderSizes[CurrencyPair::ETHBTC] = 0.001;

        $this->basePrecisions[CurrencyPair::BTCUSD] = 8;
        $this->basePrecisions[CurrencyPair::ETHUSD] = 6;
        $this->basePrecisions[CurrencyPair::ETHBTC] = 6;

        $this->quotePrecisions[CurrencyPair::BTCUSD] = 2;
        $this->quotePrecisions[CurrencyPair::ETHUSD] = 2;
        $this->quotePrecisions[CurrencyPair::ETHBTC] = 5;

    }

    public function positions()
    {
        return array();
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        return $this->feeSchedule->getFee($pair, $tradingRole, $volume);
    }

    private function getRatioRebate($tradingRole, $buySellRatio)
    {
        $rebate = 0.0;
        if ($tradingRole == TradingRole::Maker) {
            if (0.65 >= $buySellRatio && $buySellRatio > 0.60) {
                $rebate = 0.05;
            } else if (0.60 >= $buySellRatio && $buySellRatio >= 0.55) {
                $rebate = 0.10;
            } else if (0.55 >= $buySellRatio && $buySellRatio >= 0.45) {
                $rebate = 0.15;
            } else if (0.45 > $buySellRatio && $buySellRatio >= 0.40) {
                $rebate = 0.10;
            } else if (0.40 > $buySellRatio && $buySellRatio >= 0.35) {
                $rebate = 0.05;
            }
        }
        return $rebate;
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $taker = $this->tradingFee($pair, TradingRole::Taker, 0.0);
            $maker = $this->tradingFee($pair, TradingRole::Maker, 0.0);
            $feeSchedule->addPairFee($pair, $taker, $maker);
        }
        $volumes = $this->authQuery('tradevolume')[0];
        foreach ($volumes as $volume) {
            $pair =  mb_strtoupper($volume['symbol']);
            $tradingVolume = floatval($volume['total_volume_base']);
            $takerRebate = $this->getRatioRebate(TradingRole::Taker, floatval($volume['maker_buy_sell_ratio']));
            $taker = $this->tradingFee($pair, TradingRole::Taker, $tradingVolume) - $takerRebate;
            $makerRebate = $this->getRatioRebate(TradingRole::Maker, floatval($volume['maker_buy_sell_ratio']));
            $maker = $this->tradingFee($pair, TradingRole::Maker, $tradingVolume) - $makerRebate;
            $feeSchedule->replacePairFee($pair, $taker, $maker);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        $volumes = $this->authQuery('tradevolume')[0];
        // From https://gemini.com/fee-schedule/
        // This method takes into account the ratio of buy and sell orders, which
        // can give further rebates for maker trades.
        $rebate = 0.0;
        $tradingVolume = 0.0;
        foreach ($volumes as $volume) {
            if (mb_strtoupper($volume['symbol']) == $pair) {
                $tradingVolume = floatval($volume['total_volume_base']);
                $rebate = $this->getRatioRebate($tradingRole, $volume['maker_buy_sell_ratio']);
            }
        }
        return $this->tradingFee($pair, $tradingRole, $tradingVolume) - $rebate;
    }

    public function ticker($pair)
    {
        $tickerData = CurlHelper::query($this->getApiUrl() . 'pubticker' . '/' . $pair);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $tickerData['bid'];
        $t->ask = $tickerData['ask'];
        $t->last = $tickerData['last'];
        $t->volume = $tickerData['volume'][CurrencyPair::Base($pair)];

        return $t;
    }

    public function basePrecision($pair, $pairRate)
    {
        return $this->basePrecisions[$pair];
    }

    public function quotePrecision($pair, $pairRate)
    {
        return $this->quotePrecisions[$pair];
    }

    function getApiUrl()
    {
        return 'https://api.gemini.com/v1/';
    }

    protected function generateHeaders($key, $payload, $signature)
    {
        return array(
            'X-GEMINI-APIKEY: '.$key,
            'X-GEMINI-PAYLOAD: '.$payload,
            'X-GEMINI-SIGNATURE: '.$signature
        );
    }
}

