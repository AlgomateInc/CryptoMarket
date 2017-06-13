<?php

/**
 * User: jon
 * Date: 1/29/2017
 * Time: 11:00 AM
 */

namespace CryptoMarket\Exchange;

use CryptoMarket\Helper\CurlHelper;
use CryptoMarket\Helper\MongoHelper;

use CryptoMarket\Exchange\BaseExchange;
use CryptoMarket\Exchange\ILifecycleHandler;

use CryptoMarket\Record\CurrencyPair;
use CryptoMarket\Record\FeeSchedule;
use CryptoMarket\Record\OrderBook;
use CryptoMarket\Record\OrderExecution;
use CryptoMarket\Record\OrderType;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;
use CryptoMarket\Record\TradingRole;

use MongoDB\BSON\UTCDateTime;

class Yunbi extends BaseExchange implements ILifecycleHandler
{
    private $key;
    private $secret;

    private $supportedPairs = array();
    private $productId = array(); //assoc array pair->productid
    private $basePrecisions = array(); //assoc array pair->min order size

    public function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
    }

    function init()
    {
        $pairs = CurlHelper::query($this->getApiUrl() . 'markets.json');
        foreach ($pairs as $pairInfo){
            try{
                $base = CurrencyPair::Base($pairInfo['name']);
                $quote = CurrencyPair::Quote($pairInfo['name']);
                $pair = CurrencyPair::MakePair($base, $quote);

                $this->supportedPairs[] = $pair;
                $this->productId[$pair] = $pairInfo['id'];
            } catch (\Exception $e) {
            }
        }

        $this->basePrecisions[CurrencyPair::BTCCNY] = 4;
        $this->basePrecisions[CurrencyPair::DCSCNY] = 2;
        $this->basePrecisions[CurrencyPair::SCCNY] = 0;
        $this->basePrecisions[CurrencyPair::FSTCNY] = 3;
        $this->basePrecisions[CurrencyPair::REPCNY] = 3;
        $this->basePrecisions[CurrencyPair::ANSCNY] = 3;
        $this->basePrecisions[CurrencyPair::ZECCNY] = 3;
        $this->basePrecisions[CurrencyPair::ZMCCNY] = 2;
        $this->basePrecisions[CurrencyPair::GNTCNY] = 0;
        $this->basePrecisions[CurrencyPair::BTSCNY] = 2;
        $this->basePrecisions[CurrencyPair::BITCNYCNY] = 3;
    }

    public function Name()
    {
        return 'Yunbi';
    }

    public function balances()
    {
        $balance_info = $this->authQuery('members/me.json');

        $balances = array();
        foreach($this->supportedCurrencies() as $curr){
            $balances[$curr] = 0;
            foreach($balance_info['accounts'] as $balItem)
                if(strcasecmp($balItem['currency'], $curr) == 0)
                    $balances[$curr] += floatval($balItem['balance']);
        }

        return $balances;
    }

    public function tradingFee($pair, $tradingRole, $volume)
    {
        return $this->currentTradingFee($pair, $tradingRole);
    }

    public function currentFeeSchedule()
    {
        $feeSchedule = new FeeSchedule();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $taker = $this->currentTradingFee($pair, TradingRole::Taker);
            $maker = $this->currentTradingFee($pair, TradingRole::Maker);
            $feeSchedule->addPairFee($pair, $taker, $maker);
        }
        return $feeSchedule;
    }

    public function currentTradingFee($pair, $tradingRole)
    {
        // From https://yunbi.com/documents/price
        if ($pair == CurrencyPair::BTCCNY) {
            return 0.2;
        }
        return 0.1;
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
        $basePrecision = $this->basePrecision($pair, $pairRate);
        return bcpow(10, -1 * $basePrecision, $basePrecision);
    }

    public function basePrecision($pair, $pairRate)
    {
        if (array_key_exists($pair, $this->basePrecisions)) {
            return $this->basePrecisions[$pair];
        }
        return $this->basePrecisions[CurrencyPair::BTCCNY];
    }

    public function quotePrecision($pair, $pairRate)
    {
        // currency pairs are shown with 3 significant figures, or a minimum 
        // of 2 decimal places, and smallest increment is the last sig fig
        // Some currencies inexplicably have different conventions "subjectively
        // determined by the CTO"
        $order_of_magnitude = intval(floor(log10($pairRate)));
        if ($order_of_magnitude >= 0) {
            if ($pair == CurrencyPair::REPCNY) {
                return 3;
            } else {
                return 2;
            }
        } else {
            if ($pair == CurrencyPair::BITCNYCNY) {
                return 3 - $order_of_magnitude;
            } else {
                return 2 - $order_of_magnitude;
            }
        }
    }

    public function ticker($pair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . 'tickers/' . $this->productId[$pair] . '.json');

        $t = new Ticker();
        $t->currencyPair = $pair;
        $t->bid = $raw['ticker']['buy'];
        $t->ask = $raw['ticker']['sell'];
        $t->last = $raw['ticker']['last'];
        $t->volume = $raw['ticker']['vol'];

        return $t;
    }

    public function trades($pair, $sinceDate)
    {
        $tradeList = CurlHelper::query($this->getApiUrl() . 'trades.json?market=' . $this->productId[$pair]);

        $ret = array();

        foreach($tradeList as $raw) {
            if($raw['at'] < $sinceDate)
                continue;

            $t = new Trade();
            $t->currencyPair = $pair;
            $t->exchange = $this->Name();
            $t->tradeId = $raw['id'];
            $t->price = floatval($raw['price']);
            $t->quantity = floatval($raw['volume']);
            $t->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate($raw['at']));
            $t->orderType = ($raw['side'] == 'up')? OrderType::BUY : OrderType::SELL;

            $ret[] = $t;
        }

        return $ret;
    }

    public function depth($currencyPair)
    {
        $raw = CurlHelper::query($this->getApiUrl() . 'depth.json?market=' . $this->productId[$currencyPair]);

        // asks are returned in descending order, so reverse them
        $rev_asks = array_reverse($raw['asks']);
        unset($raw['asks']);
        $raw['asks'] = $rev_asks;

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

    private function submitOrder($side, $type, $pair, $quantity, $price)
    {
        $req = array(
            'volume' => "$quantity",
            'price' => "$price",
            'side' => $side,
            'market' => $this->productId[$pair],
            'ord_type' => $type
        );
        return $this->authQuery('orders.json', 'POST', $req);
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
        return $this->authQuery('order/delete.json', 'POST', array('id'=>$orderId));
    }

    public function isOrderAccepted($orderResponse)
    {
        return isset($orderResponse['id']);
    }

    public function isOrderOpenOfId($orderId)
    {
        try {
            $os = $this->authQuery('order.json', 'GET', array('id' => $orderId));
            if (isset($os['state'])) {
                return $os['state'] === 'wait';
            }
        } catch (\Exception $e) {}
        return false;
    }

    public function isOrderOpen($orderResponse)
    {
        if (!$this->isOrderAccepted($orderResponse))
            return false;

        return $this->isOrderOpenOfId($this->getOrderID($orderResponse));
    }

    public function getOrderExecutions($orderResponse)
    {
        return $this->getOrderExecutionsOfId($this->getOrderID($orderResponse));
    }

    private function getOrderExecutionsOfId($orderId)
    {
        $ret = array();
        $order_info = $this->authQuery('order.json', 'GET', array('id' => $orderId));

        foreach ($order_info['trades'] as $fill) {
            $exec = new OrderExecution();
            $exec->txid = $fill['id'];
            $exec->orderId = $orderId;
            $exec->quantity = $fill['volume'];
            $exec->price = $fill['price'];
            $exec->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate(strtotime($fill['at'])));

            $ret[] = $exec;
        }

        return $ret;
    }

    public function getTradeHistoryForPair($pair, $page=1)
    {
        $ret = array();
        $params = array('market' => $this->productId[$pair],
            'state' => 'done',
            'order_by' => 'desc',
            'page' => $page);
        // big note: we're using the orders.json endpoint here because 
        // trades/my.json is currently unusable and times out constantly
        $orders = $this->authQuery('orders.json', 'GET', $params);
            
        foreach ($orders as $order) {
            $td = new Trade();
            //$td->tradeId = $trade['id']; // not available on the orders api
            $td->orderId = $order['id'];
            $td->exchange = $this->Name();
            $td->currencyPair = $pair;
            $td->orderType = ($order['side'] == 'ask')? OrderType::SELL : OrderType::BUY;
            $td->price = $order['avg_price'];
            $td->quantity = $order['volume'];
            $td->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate(strtotime($order['created_at'])));

            $ret[] = $td;
        }
        return $ret;
    }

    public function tradeHistory($desiredCount)
    {
        // Yunbi forces you to specify the market when getting your trades, 
        // so we're forced to hammer their APIs for every currency pair.
        $num_fetched = 0;
        $ret = array();
        $alltrades = array();
        $pagecounters = array();

        // Initialize trades for all currency pairs, throw away empty ones.
        foreach ($this->supportedPairs as $pair) {
            $orders = $this->getTradeHistoryForPair($pair);
            if (count($orders) > 0) {
                $alltrades[$pair] = $orders;
                $pagecounters[$pair] = 1;
            }
        }

        while ($num_fetched < $desiredCount)
        {
            // Find the pair with the latest trade
            $next_pair = null;
            foreach ($alltrades as $pair => $orders) {
                if (isset($next_pair)) {
                    if ($orders[0]->timestamp > $next_trade->timestamp) {
                        $next_pair = $pair;
                    }
                } else {
                    $next_pair = $pair;
                }
            }
            if (is_null($next_pair)) // nothing was found, we're done
                break;

            // Shift the first element off the orders
            $next_trade = array_shift($alltrades[$next_pair]);
            $ret[] = $next_trade;

            // Fetch the next page of trades
            if (empty($alltrades[$next_pair])) {
                $pagecounters[$next_pair]++;
                $next_trades = $this->getTradeHistoryForPair($next_pair, $pagecounters[$next_pair]);
                if (empty($next_trades)) {
                    unset($alltrades[$next_pair]); // none found, unset it
                } else {
                    $alltrades[$next_pair] = $next_trades;
                }
            }

            $num_fetched += 1;
        }
        return $ret;
    }

    public function getOrderID($orderResponse)
    {
        return $orderResponse['id'];
    }

    private function getApiBase()
    {
        return 'https://yunbi.com';
    }

    private function getApiTail()
    {
        return '/api/v2/';
    }

    private function getApiUrl()
    {
        return $this->getApiBase() . $this->getApiTail();
    }

    private function signature($request_path, $body, $method)
    {
        $what = $method.'|'.$this->getApiTail().$request_path.'|'.$body;

        return hash_hmac("sha256", $what, $this->secret);
    }

    private function authQuery($request_path, $method='GET', $body=array()) {
        // Adapated from https://gist.github.com/lgn21st/5de1995bff6334824406
        $body['tonce'] = round(microtime(true) * 1000);
        $body['access_key'] = $this->key;
        ksort($body);
        $body_string = http_build_query($body);
        $sig = $this->signature($request_path, $body_string, $method);

        $body_string .= '&signature='.$sig;
        if ($method=='GET') {
            return CurlHelper::query($this->getApiUrl() . $request_path.'?'.$body_string);
        } else {
            return CurlHelper::query($this->getApiUrl() . $request_path, $body_string, array(), $method);
        }
    }
}

