<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\DateHelper;
use CryptoMarket\Helper\NonceFactory;

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

class Bittrex extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $nonce_factory;

    private $supported_pairs = array();
    private $min_order_sizes = array(); //assoc array pair->minordersize
    private $product_ids = array(); //assoc array pair->productid

    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->nonce_factory = new NonceFactory();
    }

    public function init()
    {
        $mkts = $this->public_query('getmarkets');
        foreach ($mkts as $mkt) {
            $quote = $mkt['BaseCurrency'];
            $base = $mkt['MarketCurrency'];
            if ($quote == Currency::USDT) {
                $quote = Currency::USD;
            }
            if ($base == Currency::USDT) {
                $base = Currency::USD;
            }

            $pair = CurrencyPair::MakePair(mb_strtoupper($base), mb_strtoupper($quote));
            $this->supported_pairs[] = $pair;
            $this->min_order_sizes[$pair] = $mkt['MinTradeSize'];
            $this->product_ids[$pair] = $mkt['MarketName'];
        }
    }

    public function Name()
    {
        return 'Bittrex';
    }

    public function balances()
    {
        $res = $this->account_query('getbalances?');
        $ret = [];
        foreach ($res as $bal) {
            $ret[$bal['Currency']] = $bal['Balance'];
        }
        return $ret;
    }

    public function tradingFee($pair, $tradingRole, $thirty_day_volume)
    {
        return 0.25;
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        //https://support.bittrex.com/hc/en-us/articles/115000199651-What-fees-does-Bittrex-charge-
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $feeSchedule->addPairFee($pair, 0.25, 0.25);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        return 0.25;
    }

    private function makeTransaction($wd, $transType)
    {
        $tx = new Transaction();
        $tx->exchange = $this->Name();
        $tx->type = $transType;
        $tx->id = $wd['TxId'];
        $tx->currency = $wd['Currency'];
        $tx->amount = floatval($wd['Amount']);
        $dt = new \DateTime($wd['LastUpdated']);
        $tx->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($dt->getTimestamp()));
        return $tx;
    }

    public function transactions()
    {
        $withdrawals = $this->account_query('getwithdrawalhistory/?');
        $ret = [];
        foreach ($withdrawals as $wd) {
            $ret[] = $this->makeTransaction($wd, TransactionType::Debit);
        }
        $deposits = $this->account_query('getdeposithistory/?');
        foreach ($deposits as $dp) {
            $ret[] = $this->makeTransaction($dp, TransactionType::Credit);
        }
        return $ret;
    }

    public function supportedCurrencyPairs()
    {
        return $this->supported_pairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->min_order_sizes[$pair];
    }

    public function tickers()
    {
        $raw = $this->public_query('getmarketsummaries');
        $ret = array();
        foreach ($raw as $mkt) {
            $t = new Ticker();
            $t->currencyPair = $this->pair_of_product_id($mkt['MarketName']);
            $t->bid = $mkt['Bid'];
            $t->ask = $mkt['Ask'];
            $t->last = $mkt['Last'];
            $t->volume = $mkt['Volume'];
            $ret[] = $t;
        }
        return $ret;
    }

    public function ticker($pair)
    {
        $raw = $this->public_query('getticker?market=' . $this->product_ids[$pair]);

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['Bid'];
        $t->ask = $raw['Ask'];
        $t->last = $raw['Last'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $trades = $this->public_query('getmarkethistory?market=' . $this->product_ids[$pair]);
        $ret = array();
        foreach($trades as $raw) {
            $dt = new \DateTime($raw['TimeStamp']);
            if ($dt->getTimestamp() < $sinceDate) {
                break;
            }

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['Id'];
            $t->price = $raw['Price'];
            $t->quantity = $raw['Quantity'];
            $t->orderType = mb_strtoupper($raw['OrderType']);
            $t->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($dt->getTimestamp()));

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currency_pair)
    {
        $raw = $this->public_query('getorderbook?market=' . $this->product_ids[$currency_pair] . '&type=both');
        $raw_book = [ 'bids' => $raw['buy'], 'asks' => $raw['sell'] ];
        $book = new OrderBook($raw_book);
        return $book;
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->market_query('buylimit/?market='.$this->product_ids[$pair].'&quantity='.$quantity.'&rate='.$price.'&');
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->market_query('selllimit/?market='.$this->product_ids[$pair].'&quantity='.$quantity.'&rate='.$price.'&');
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
        return $this->market_query('cancel/?uuid='.$orderId.'&');
    }

    public function isOrderAccepted($orderResponse)
    {
        return array_key_exists('uuid', $orderResponse);
    }

    public function isOrderOpen($orderResponse)
    {
        $open_orders = $this->market_query('getopenorders/?');
        foreach ($open_orders as $order) {
            if ($order['OrderUuid'] == $orderResponse['uuid']) {
                return true;
            }
        }
        return false;
    }

    private function get_api_url()
    {
        return 'https://bittrex.com/api/v1.1';
    }

    public function getOrderExecutions($orderResponse)
    {
        $order_info = $this->account_query('getorder/?uuid='.$orderResponse['uuid'].'&');
        $ret = [];
        if ($order_info['Quantity'] != $order_info['QuantityRemaining']) {
            // fake it since they don't provide everything
            $exec = new OrderExecution();
            $exec->txid = $order_info['Sentinel'];
            $exec->orderId = $order_info['OrderUuid'];
            $exec->quantity = $order_info['Quantity'] - $order_info['QuantityRemaining'];
            $exec->price = $order_info['PricePerUnit'];
            $dt = null;
            if ($order_info['Closed'] != null) {
                $dt = new \DateTime($order_info['Closed']);
            } else {
                $dt = new \DateTime($order_info['Opened']);
            }
            $exec->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($dt->getTimestamp()));
            $ret[] = $exec;
        }
        return $ret;
    }

    public function tradeHistory($desiredCount)
    {
        $trades = $this->account_query('getorderhistory/?');
        $ret = [];
        foreach ($trades as $trade) {
            if (count($ret) >= $desiredCount) {
                break;
            }
            if ($trade['Quantity'] != $trade['QuantityRemaining']){
                $td = new Trade();
                $td->tradeId = null;
                $td->orderId = $trade['OrderUuid'];
                $td->exchange = $this->Name();
                $td->currencyPair = $this->pair_of_product_id($trade['Exchange']);
                $td->orderType = (strpos($trade['OrderType'], 'SELL') !== false) ? OrderType::SELL : OrderType::BUY;
                $td->price = $trade['PricePerUnit'];
                $td->quantity = $trade['Quantity'] - $trade['QuantityRemaining'];
                $dt = new \DateTime($trade['Closed']);
                $td->timestamp = new UTCDateTime(DateHelper::mongoDateOfPHPDate($dt->getTimestamp()));
                $ret[] = $td;
            }
        }
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['uuid'];
    }

    private function market_query($request_path)
    {
        return $this->private_query($this->get_api_url() . '/market/' . $request_path);
    }

    private function account_query($request_path)
    {
        return $this->private_query($this->get_api_url() . '/account/' . $request_path);
    }

    private function private_query($request_path, $params = '')
    {
        $uri = $request_path . 'apikey=' .  $this->key . '&nonce=' . $this->nonce_factory->get();
        $sign = hash_hmac('sha512', $uri, $this->secret);
        $headers = ['apisign: ' . $sign];
        $res = CurlHelper::query($uri, null, $headers);
        if ($res['success'] === false) {
            throw new \Exception('Error on query: ' . $res['message']);
        }
        return $res['result'];
    }

    private function public_query($request_path)
    {
        $res = CurlHelper::query($this->get_api_url() . '/public/' . $request_path);
        if ($res['success'] === false) {
            throw new \Exception('Error on query: ' . $res['message']);
        }
        return $res['result'];
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
