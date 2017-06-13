<?php

namespace CryptoMarket\Account;

use CryptoMarket\Account\IAccount;
use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Helper\MongoHelper;
use CryptoMarket\Record\Currency;
use CryptoMarket\Record\Transaction;

use MongoDB\BSON\UTCDateTime;

require_once(__DIR__.'/../mongo_helper.php');

class JPMChase implements IAccount
{
    private $mailbox;
    private $user;
    private $pwd;

    public function __construct($mailbox, $username, $password){
        $this->mailbox = $mailbox;
        $this->user = $username;
        $this->pwd = $password;
    }

    public function Name()
    {
        return 'JPMChase';
    }

    private function getLatestMessage()
    {
        $conn = imap_open($this->mailbox, $this->user, $this->pwd);
        if($conn === false)
            throw new \Exception('Could not connect to IMAP server for JPM balance');

        //get the most recent message to parse
        $msgCount = imap_num_msg($conn);
        if($msgCount > 0)
        {
            $bodyText = imap_fetchbody($conn,$msgCount,1.2);
            if(!mb_strlen($bodyText)>0){
                $bodyText = imap_fetchbody($conn,$msgCount,1);
            }

            return $bodyText;
        }

        return null;
    }

    public function balances()
    {
        //default response
        $balances = array();
        $balances[Currency::USD] = 0;

        $bodyText = $this->getLatestMessage();
        if($bodyText != null)
        {
            //parse message text for balance
            $res = preg_match('/End of day balance: \$([\d,.]+)/', $bodyText, $matches);
            if($res != 1)
                throw new \Exception('Message retrieved does not contain account balance: ' . $bodyText);

            $balances[Currency::USD] = str_replace(',','', $matches[1]);
        }

        return $balances;
    }

    public function transactions()
    {
        $transactions = array();

        $bodyText = $this->getLatestMessage();
        if($bodyText != null)
        {
            $dep = $this->matchTxAmounts($bodyText, "Deposit ", TransactionType::Credit);
            $wd = $this->matchTxAmounts($bodyText, "Withdrawal ", TransactionType::Debit);

            $transactions = array_merge($transactions, $dep);
            $transactions = array_merge($transactions, $wd);
        }

        return $transactions;
    }

    private function getSummaryDate($bodyText)
    {
        $res = preg_match('/Here is your Chase account summary for (.+)\./', $bodyText, $matches);
        if($res != 1)
            throw new \Exception('Message retrieved does not contain date: ' . $bodyText);

        return strtotime($matches[1]);
    }

    private function matchTxAmounts($bodyText, $prefix, $txType)
    {
        $txList = array();

        $updateDate = $this->getSummaryDate($bodyText);

        ///////////
        $res = preg_match('/' . $prefix . '\$([\d,.]+)/', $bodyText, $matches);
        for($i = 1; $i < count($matches); $i++)
        {
            $tx = new Transaction();
            $tx->exchange = ExchangeName::JPMChase;
            $tx->id = hash('sha256',$bodyText . $updateDate . $txType . $i);
            $tx->type = $txType;
            $tx->currency = Currency::USD;
            $tx->amount = str_replace(',','', $matches[$i]);
            $tx->timestamp = new UTCDateTime(MongoHelper::mongoDateOfPHPDate($updateDate));

            $txList[] = $tx;
        }

        return $txList;
    }
}

