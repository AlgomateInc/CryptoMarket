<?php

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ILifecycleHandler;

use CryptoMarket\Record\Currency;
use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\FeeScheduleItem;
use CryptoMarket\Record\FeeScheduleList;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderExecution;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\TradingRole;

use MongoDB\BSON\UTCDateTime;

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 11/3/2016
 * Time: 6:31 AM
 */

class Gdax extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;
    private $passphrase;

    private $supportedPairs = array();
    private $minOrderSizes = array(); //assoc array pair->minordersize
    private $productIds = array(); //assoc array pair->productid
    private $quotePrecisions = array(); //assoc array pair->quotePrecision

    private $feeSchedule; //assoc array pair->quotePrecision

    public function __construct($key, $secret, $passphrase) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;

        // From https://docs.gdax.com/#fees
        // Note, these volume thresholds are in % of total
        $genericFeeSchedule = new FeeScheduleList();
        $genericFeeSchedule->push(new FeeScheduleItem(0.0, 1.0e-2, 0.25, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(1.0e-2, 2.5e-2, 0.24, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(2.5e-2, 5.0e-2, 0.22, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(5.0e-2, 1.0e-1, 0.19, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(1.0e-1, 2.0e-1, 0.15, 0.0));
        $genericFeeSchedule->push(new FeeScheduleItem(2.0e-1, INF, 0.10, 0.0));

        $this->feeSchedule = new FeeSchedule();
        $this->feeSchedule->setFallbackFees($genericFeeSchedule);
    }

    function init()
    {
        // From https://docs.gdax.com/#fees, all ETH markets have .30% fee at
        // the first band.
        $ethFeeSchedule = new FeeScheduleList();
        $ethFeeSchedule->push(new FeeScheduleItem(0.0, 1.0, 0.30, 0.0));
        $ethFeeSchedule->push(new FeeScheduleItem(1.0, 2.5, 0.24, 0.0));
        $ethFeeSchedule->push(new FeeScheduleItem(2.5, 5.0, 0.22, 0.0));
        $ethFeeSchedule->push(new FeeScheduleItem(5.0, 10.0, 0.19, 0.0));
        $ethFeeSchedule->push(new FeeScheduleItem(10.0, 20.0, 0.15, 0.0));
        $ethFeeSchedule->push(new FeeScheduleItem(20.0, INF, 0.10, 0.0));

        $pairs = CurlHelper::query($this->getApiUrl() . '/products');
        foreach($pairs as $pairInfo){
            try{
                $pair = $pairInfo['base_currency'] . $pairInfo['quote_currency'];
                $base = CurrencyPair::Base($pair); //checks the format
                if ($base == Currency::ETH) {
                    $this->feeSchedule->addPairFees($pair, $ethFeeSchedule);
                }

                $this->supportedPairs[] = mb_strtoupper($pair);
                $this->minOrderSizes[$pair] = $pairInfo['base_min_size'];
                $this->productIds[$pair] = $pairInfo['id'];
                $this->quotePrecisions[$pair] = intval(abs(floor(log10($pairInfo['quote_increment']))));
            }catch(\Exception $e){}
        }
    }

    public function Name()
    {
        return 'Gdax';
    }

    public function balances()
    {
        $balance_info = $this->authQuery('/accounts');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += $balItem['available'];
        }

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $thirty_day_volume)
    {
        // From https://docs.gdax.com/#fees
        // Makers are always 0.0
        if ($tradingRole == TradingRole::Maker) {
            return 0.0;
        }

        // For taker fees, first get the total traded volume for the pair over 
        // the last 30 days, then have given volume as a percentage.
        $SECONDS_PER_THIRTY = 30 * 24 * 60 * 60;
        $SECONDS_PER_TEN = 10 * 24 * 60 * 60; // larger granularities fail
        $FORMAT = "Y-m-d";
        $nowTs = date($FORMAT, time());
        $prevTs = date($FORMAT, time() - $SECONDS_PER_THIRTY);
        $query = $this->getApiUrl() . '/products/' . $this->productIds[$pair] . 
            "/candles?start=$prevTs&end=$nowTs&granularity=$SECONDS_PER_TEN";
        $candles = CurlHelper::query($query);
        $totalVolume = 0.0;
        foreach ($candles as $candle){
            $totalVolume += $candle[5];
        }

        $volumePercentage = $thirty_day_volume / $totalVolume;
        return $this->feeSchedule->getFee($pair, $tradingRole, $volumePercentage);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $taker = $this->feeSchedule->getFee($pair, TradingRole::Taker);
            $maker = $this->feeSchedule->getFee($pair, TradingRole::Maker);
            $feeSchedule->addPairFee($pair, $taker, $maker);
        }

        // Get user's total traded volume in the pair over the last 30 days
        $userVolumes = $this->authQuery('/users/self/trailing-volume', 'GET');
        foreach ($userVolumes as $volume) {
            $pair = $this->currencyPairOfProductId($volume['product_id']);
            $volumePercentage = floatval(bcdiv($volume['volume'], $volume['exchange_volume'], 4));
            $taker = $this->feeSchedule->getFee($pair, TradingRole::Taker, $volumePercentage);
            $maker = $this->feeSchedule->getFee($pair, TradingRole::Maker, $volumePercentage);
            $feeSchedule->replacePairFee($pair, $taker, $maker);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        if ($tradingRole == TradingRole::Maker) {
            return $this->tradingFee($pair, $tradingRole, 0.0);
        }

        // Get user's total traded volume in the pair over the last 30 days
        $userVolumes = $this->authQuery('/users/self/trailing-volume', 'GET');
        foreach ($userVolumes as $volume) {
            if ($volume['product_id'] == $this->productIds[$pair]) {
                $volumePercentage = floatval(bcdiv($volume['volume'], $volume['exchange_volume'], 4));
                return $this->feeSchedule->getFee($pair, $tradingRole, $volumePercentage);
            }
        }
        return $this->feeSchedule->getFee($pair, $tradingRole, 0.0);
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    public function supportedCurrencyPairs()
    {
        return $this->supportedPairs;
    }

    public function minimumOrderSize($pair, $pairRate)
    {
        return $this->minOrderSizes[$pair];
    }

    public function basePrecision($pair, $pairRate)
    {
        $base = CurrencyPair::Base($pair);
        $quote = CurrencyPair::Quote($pair);
        if (false == Currency::isFiat($base) && false == Currency::isFiat($quote)) {
            return $this->quotePrecision($pair, $pairRate);
        } else {
            return parent::basePrecision($pair, $pairRate);
        }
    }

    public function quotePrecision($pair, $pairRate)
    {
        return $this->quotePrecisions[$pair];
    }

    public function ticker($pair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . '/products/' . $this->productIds[$pair] . '/ticker');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['bid'];
        $t->ask = $raw['ask'];
        $t->last = $raw['price'];
        $t->volume = $raw['volume'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = CurlHelper::query($this->getApiUrl() . '/products/' . $this->productIds[$pair] . '/trades');

        $ret = array();

        foreach($tradeList as $raw) {
            $tradeTime = strtotime($raw['time']);
            if($tradeTime < $sinceDate)
                continue;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['trade_id'];
            $t->price = (float) $raw['price'];
            $t->quantity = (float) $raw['size'];
            $t->timestamp = new UTCDateTime();
            $t->orderType = ($raw['side'] == 'buy')? OrderType::SELL : OrderType::BUY;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . '/products/' . $this->productIds[$currencyPair] .
            '/book?level=2');

        $book = new OrderBook($raw);

        return $book;
    }

    public function buy($pair, $quantity, $price)
    {
        return $this->submitOrder('buy', 'limit', $pair, $quantity, $price);
    }

    public function sell($pair, $quantity, $price)
    {
        return $this->submitOrder('sell', 'limit', $pair, $quantity, $price);
    }

    // Used for testing the order executions
    public function submitMarketOrder($side, $pair, $quantity)
    {
        return $this->submitOrder($side, 'market', $pair, $quantity, 0);
    }

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $req = array(
            'size' => "$quantity",
            'price' => "$price",
            'side' => $side,
            'product_id' => $this->productIds[$pair],
            'type' => $type
        );
        return $this->authQuery('/orders', 'POST', $req);
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
        return $this->authQuery('/orders/' . $orderId, 'DELETE');
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['id']);
    }

    public function isOrderOpen($orderResponse)
    {
        if(!$this->isOrderAccepted($orderResponse))
            return false;

        try {
            $os = $this->authQuery('/orders/' . $this->getOrderId($orderResponse));
            if (isset($os['status'])) {
                return $os['status'] === 'open' || $os['status'] === 'pending';
            }
        } catch (\Exception $e) { }
        return false;
    }

    public function getOrderExecutions($orderResponse)
    {
        return $this->getOrderExecutionsOfId($this->getOrderId($orderResponse));
    }

    private function getOrderExecutionsOfId($orderId)
    {
        $ret = array();
        $after_cursor = '';
        do
        {
            $url = '/fills?order_id='.$orderId.$after_cursor;
            $order_fills = $this->authQuery($url, 'GET', '', true);

            // Probably not necessary for this function, but in case an order
            // has over 100 executions, we're safe.
            // See https://docs.gdax.com/#pagination
            if (isset($order_fills['header']['Cb-After'])) {
                $after_cursor = '&after='.$order_fills['header']['Cb-After'];
            } else {
                $after_cursor = '';
            }

            foreach ($order_fills['body'] as $fill) {
                if ($fill['order_id'] === $orderId)
                {
                    $exec = new OrderExecution();
                    $exec->txid = $fill['trade_id'];
                    $exec->orderId = $fill['order_id'];
                    $exec->quantity = $fill['size'];
                    $exec->price = $fill['price'];
                    $exec->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate(strtotime($fill['created_at'])));

                    $ret[] = $exec;
                }
            }
        } while ($after_cursor != '');

        return $ret;
    }

    public function tradeHistory($desiredCount)
    {
        $num_fetched = 0;
        $ret = array();
        $after_cursor = '';
        do
        {
            // The header contains a special 'Cb-After' parameter to use in
            // subsequent requests, see https://docs.gdax.com/#pagination
            // Alternatively, this query could get the orders using
            // '/orders?status=all' but this doesn't retrieve the trade id.
            $orders = $this->authQuery('/fills'.$after_cursor, 'GET', '', true);
            if (isset($orders['header']['Cb-After'])) {
                $after_cursor = '?after='.$orders['header']['Cb-After'];
            }
            if(count($orders['body']) === 0)
                break;
            foreach($orders['body'] as $order) {
                $td = new Trade();
                $td->tradeId = $order['trade_id'];
                $td->orderId = $order['order_id'];
                $td->exchange = $this->Name();
                $td->currencyPair = $this->currencyPairOfProductId($order['product_id']);
                $td->orderType = ($order['side'] == 'sell')? OrderType::SELL : OrderType::BUY;
                $td->price = $order['price'];
                $td->quantity = $order['size'];
                $td->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate(strtotime($order['created_at'])));

                $ret[] = $td;
                $num_fetched += 1;
                if($num_fetched >= $desiredCount)
                    break;
            }
        }
        while ($num_fetched < $desiredCount);
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }

    private function getApiUrl()
    {
        return 'https://api.gdax.com';
    }

    private function signature($request_path, $body, $timestamp, $method)
    {
        $what = $timestamp.$method.$request_path.$body;

        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }

    private function authQuery($request_path, $method='GET', $body='', $return_headers=false) {
        $ts = time();
        $body = is_array($body) ? json_encode($body) : $body;
        $sig = $this->signature($request_path, $body, $ts, $method);

        $headers = array(
            'Content-Type: application/json',
            'CB-ACCESS-KEY: ' . $this->key,
            'CB-ACCESS-SIGN: ' . $sig,
            'CB-ACCESS-TIMESTAMP: ' . $ts,
            'CB-ACCESS-PASSPHRASE: ' . $this->passphrase
        );

        return CurlHelper::query($this->getApiUrl() . $request_path, $body, $headers, $method, $return_headers);
    }

    // Helper function for converting from the GDAX product id, e.g. "BTC-USD",
    // to the standard representation in the application.
    private function currencyPairOfProductId($productId)
    {
        foreach($this->productIds as $pair=>$pid) {
            if ($productId === $pid)
                return $pair;
        }
        throw new \Exception("Product id not found: $productId");
    }
}

