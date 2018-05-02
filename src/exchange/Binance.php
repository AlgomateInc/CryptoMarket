<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\DateHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ILifecycleHandler;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderExecution;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\Transaction;
use CryptoMarket\Record\TransactionType;

use MongoDB\BSON\UTCDateTime;

class Binance extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;

    private $supported_pairs = array();
    private $min_order_sizes = array(); //assoc array pair->minordersize
    private $product_ids = array(); //assoc array pair->productid
    private $quote_precisions = array(); //assoc array pair->quotePrecision
    private $base_precisions = array(); //assoc array pair->basePrecision
    private $maker_fee = null;
    private $taker_fee = null;

    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    public function init()
    {
        $all_info = $this->public_query('exchangeInfo');
        foreach ($all_info['symbols'] as $sym) {
            $base = $sym['baseAsset'];
            $quote = $sym['quoteAsset'];
            if ($quote == Currency::USDT) {
                $quote = Currency::USD;
            }
            if ($base == Currency::USDT) {
                $base = Currency::USD;
            }
            $pair = CurrencyPair::MakePair(mb_strtoupper($base), mb_strtoupper($quote));
            $this->supported_pairs[] = $pair;
            $this->product_ids[$pair] = $sym['symbol'];
            foreach ($sym['filters'] as $filter) {
                if ($filter['filterType'] == 'LOT_SIZE') {
                    $this->min_order_sizes[$pair] = $filter['minQty'];
                    $this->base_precisions[$pair] = intval(abs(floor(log10(floatval($filter['stepSize'])))));
                }
                if ($filter['filterType'] == 'PRICE_FILTER') {
                    $this->quote_precisions[$pair] = intval(abs(floor(log10(floatval($filter['tickSize'])))));
                }
            }
        }

        $res = $this->private_query('account');
        // comes in as basis points
        $this->maker_fee = $res['makerCommission'] / 100.0;
        $this->taker_fee = $res['takerCommission'] / 100.0;
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
        $res = $this->private_query('account');
        $ret = [];
        foreach ($res['balances'] as $bal) {
            $ret[$bal['asset']] = $bal['free'] + $bal['locked'];
        }
        return $ret;
    }

    public function tradingFee($pair, $tradingRole, $thirty_day_volume)
    {
        return $this->currentTradingFee($pair, $tradingRole);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $feeSchedule->addPairFee($pair, $this->taker_fee, $this->maker_fee);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        if ($tradingRole == TradingRole::Maker) {
            return $this->maker_fee;
        }
        return $this->taker_fee;
    }

    public function supportedCurrencyPairs()
    {
        return $this->supported_pairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->min_order_sizes[$pair];
    }

    public function basePrecision($pair, $pairRate)
    {
        return $this->base_precisions[$pair];
    }

    public function quotePrecision($pair, $pairRate)
    {
        return $this->quote_precisions[$pair];
    }

    public function tickers()
    {
        $results = $this->public_query('ticker/24hr');
        $ret = [];
        foreach ($results as $res) {
            $t = new Ticker();
            $t->currencyPair = $this->pair_of_product_id($res['symbol']);
            $t->bid = $res['bidPrice'];
            $t->ask = $res['askPrice'];
            $t->last = $res['lastPrice'];
            $t->volume = $res['volume'];
            $ret[] = $t;
        }
        return $ret;
    }

    public function ticker($pair)
    {
        $res = $this->public_query('ticker/24hr?symbol='.$this->product_ids[$pair]);
        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $res['bidPrice'];
        $t->ask = $res['askPrice'];
        $t->last = $res['lastPrice'];
        $t->volume = $res['volume'];
        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $res = $this->public_query('trades?symbol='.$this->product_ids[$pair]);
        $ret = [];
        foreach ($res as $raw) {
            if ($raw['time'] / 1000 > $sinceDate) {
                $t = new Trade();
                $t->currencyPair = $pair;
                $t->exchange = $this->Name();
                $t->tradeId = $raw['id'];
                $t->price = $raw['price'];
                $t->quantity = $raw['qty'];
                $t->orderType = $raw['isBuyerMaker'] ? OrderType::BUY : OrderType::SELL;
                $t->timestamp = new UTCDateTime($raw['time']);
                $ret[] = $t;
            }
        }
        return $ret;
    }

    public function depth($pair)
    {
        $res = $this->public_query('depth?symbol='.$this->product_ids[$pair]);
        return new OrderBook($res);
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->submit_order($pair, $quantity, $price, 'BUY');
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->submit_order($pair, $quantity, $price, 'SELL');
    }

    private function submit_order($pair, $quantity, $price, $side)
    {
        $price_string = number_format($price, $this->quotePrecision($pair, $price), '.', '');
        $quantity_string = number_format($quantity, $this->basePrecision($pair, $price), '.', '');
        $query_string = 'symbol='.$this->product_ids[$pair].'&side='.$side.'&type=LIMIT&timeInForce=GTC&quantity='.$quantity_string.'&price='.$price_string;
        return $this->private_query('order', $query_string, 'POST');
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
        $query_string = 'symbol='.$orderId['symbol'].'&orderId='.$orderId['orderId'];
        return $this->private_query('order', $query_string, 'DELETE');
    }

    public function isOrderAccepted($orderResponse)
    {
        return array_key_exists('orderId', $orderResponse);
    }
    
    public function isOrderOpen($orderResponse)
    {
        if (array_key_exists('orderId', $orderResponse)) {
            $query_string = 'symbol='.$orderResponse['symbol'].'&orderId='.$orderResponse['orderId'];
            $res = $this->private_query('order', $query_string);
            return $res['status'] == 'NEW' || $res['status'] == 'PARTIALLY_FILLED';
        }
        return false;
    }

    public function transactions()
    {
        // Not provided as of 4/25/2018
    }

    public function getOrderExecutions($orderResponse)
    {
        $ret = [];
        if (array_key_exists('orderId', $orderResponse)) {
            $query_string = 'symbol='.$orderResponse['symbol'].'&orderId='.$orderResponse['orderId'];
            $res = $this->private_query('order', $query_string);
            if ($res['status'] == 'FILLED' || $res['status'] == 'PARTIALLY_FILLED') {
                $exec = new OrderExecution();
                $exec->txid = $res['clientOrderId'];
                $exec->orderId = $res['orderId'];
                $exec->quantity = $res['executedQty'];
                $exec->price = $res['price'];
                $exec->timestamp = new UTCDateTime($res['time']);
                $ret[] = $exec;
            }
        }
        return $ret;
    }

    public function trade_cmp($a, $b)
    {
        return $a->timestamp->toDateTime() < $b->timestamp->toDateTime();
    }

    public function tradeHistory($desiredCount)
    {
        $ret = [];
        $all_trades = [];
        foreach ($this->supported_pairs as $pair) {
            $res = $this->private_query('myTrades', 'symbol='.$this->product_ids[$pair]);
            foreach ($res as $trade) {
                $t = new Trade();
                $t->tradeId = $trade['id'];
                $t->orderId = $trade['orderId'];
                $t->exchange = $this->Name();
                $t->currencyPair = $pair;
                $t->orderType = $trade['isBuyer'] ? OrderType::BUY : OrderType::SELL;
                $t->price = $trade['price'];
                $t->quantity = $trade['qty'];
                $t->timestamp = new UTCDateTime($trade['time']);
                $all_trades[] = $t;
            }
        }
        usort($all_trades, [$this, "trade_cmp"]);
        $i = 0;
        while ($i < $desiredCount && $i < count($all_trades)) {
            $ret[] = $all_trades[$i];
            ++$i;
        }
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return [ 'symbol' => $orderResponse['symbol'],
            'orderId' => $orderResponse['orderId']];
    }

    private function get_api_url()
    {
        return 'https://api.binance.com/api/';
    }

    private function public_query($request_path)
    {
        return CurlHelper::query($this->get_api_url().'v1/'.$request_path);
    }

    private function private_query($endpoint, $query_string = null, $verb = 'GET')
    {
        $uri = $this->get_api_url().'v3/'.$endpoint.'?';
        if ($query_string == null) {
            $query_string = 'timestamp=' . (time() * 1000);
        } else {
            $query_string = $query_string . '&timestamp=' . (time() * 1000);
        }
        $sign = hash_hmac('sha256', $query_string, $this->secret);
        $headers = ['X-MBX-APIKEY: ' . $this->key];
        return CurlHelper::query($uri.$query_string.'&signature=' . $sign, null, $headers, $verb);
    }

    private function pair_of_product_id($product_id)
    {
        foreach($this->product_ids as $pair=>$pid) {
            if ($product_id === $pid)
                return $pair;
        }
        throw new \Exception("Product id not found: $product_id");
    }
}

