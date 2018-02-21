<?php

namespace CryptoMarket\Helper;

class CurlHelper
{
    // Temporary function, copied from pecl_http, use until the move to PHP7
    static function httpParseHeaders( $header )
    {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', 
                    function ($m) { return mb_strtoupper($m[0]); },
                    mb_strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
    }

    static function query($url, 
            $post_data = null,
            $headers = array(),
            $verb = null,
            $return_headers = false){
        static $ch = null;
        if (is_null($ch)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FAILONERROR, true); // set to false for debugging error messages in response
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
        }
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);

        if (isset($post_data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, $return_headers);

        $res = curl_exec($ch);
        if ($res === false) {
            throw new \Exception('Could not get reply: '.curl_error($ch));
        }

        if ($return_headers) {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = mb_substr($res, 0, $header_size);
            $header_dec = self::httpParseHeaders($header);
            $body = mb_substr($res, $header_size);
            $body_dec = json_decode($body, true);
            $err = json_last_error();
            if ($err !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid data received\nError: $err\nServer returned:\n $res");
            }
            return array('header' => $header_dec, 'body' => $body_dec);
        }
        else {
            $dec = json_decode($res, true);
            $err = json_last_error();
            if ($err !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid data received\nError: $err\nServer returned:\n $res");
            }
            return $dec;
        }
    }
}

