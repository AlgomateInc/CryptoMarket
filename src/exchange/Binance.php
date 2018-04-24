<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\DateHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ILifecycleHandler;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\Ticker;

class Binance extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;

    private $supported_pairs = array();
    private $min_order_sizes = array(); //assoc array pair->minordersize
    private $product_ids = array(); //assoc array pair->productid
    private $quotePrecisions = array(); //assoc array pair->quotePrecision

    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    public function init()
    {
    }

    public function Name()
    {
        return 'Binance';
    }

    public function supports($currency_pair)
    {
        return in_array($currency_pair, $this->supported_pairs);
    }

    public function balances()
    {
    }

    public function tradingFee($pair, $tradingRole, $thirty_day_volume)
    {
    }

    public function currentFeeSchedule()
    {
    }

    public function currentTradingFee($pair, $tradingRole)
    {
    }

    public function transactions()
    {
    }

    public function supportedCurrencyPairs()
    {
        return $this->supported_pairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
    }

    public function basePrecision($pair, $pairRate)
    {
    }

    public function quotePrecision($pair, $pairRate)
    {
    }

    public function tickers()
    {
    }

    public function ticker($pair)
    {
    }

    public function trades($pair, $sinceDate)
    {
    }

    public function depth($currency_pair)
    {
    }

    public function buy($pair, $quantity, $price)
    {
    }

    public function sell($pair, $quantity, $price)
    {
    }

    public function activeOrders()
    {
        // TODO: Implement activeOrders() method.
    }

    public function hasActiveOrders()
    {
        // TODO: Implement hasActiveOrders() method.
    }

    public function cancel($orderId)
    {
    }

    public function isOrderAccepted($orderResponse)
    {
    }

    public function isOrderOpen($orderResponse)
    {
    }

    private function get_api_url()
    {
    }

    public function getOrderExecutions($orderResponse)
    {
    }

    public function tradeHistory($desiredCount)
    {
    }

    public function getOrderID($orderResponse)
    {
    }

    private function public_query($request_path)
    {
    }

    private function pair_of_product_id($product_id)
    {
    }
}

