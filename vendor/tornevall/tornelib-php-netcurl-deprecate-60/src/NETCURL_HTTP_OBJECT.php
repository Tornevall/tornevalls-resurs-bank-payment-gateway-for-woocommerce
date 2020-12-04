<?php

namespace TorneLIB;

/**
 * Class NETCURL_AUTH_TYPES Obsolete.
 *
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0.0
 * @deprecated Deprecated class. Do not use.
 */
class NETCURL_HTTP_OBJECT
{
    private $NETCURL_HEADER;
    private $NETCURL_BODY;
    private $NETCURL_CODE;
    private $NETCURL_PARSED;
    private $NETCURL_URL;
    private $NETCURL_IP;

    public function __construct($header = [], $body = '', $code = 0, $parsed = '', $url = '', $ip = '')
    {
        $this->NETCURL_HEADER = $header;
        $this->NETCURL_BODY = $body;
        $this->NETCURL_CODE = $code;
        $this->NETCURL_PARSED = $parsed;
        $this->NETCURL_URL = $url;
        $this->NETCURL_IP = $ip;
    }

    public function getHeader()
    {
        return $this->NETCURL_HEADER;
    }

    public function getBody()
    {
        return $this->NETCURL_BODY;
    }

    public function getCode()
    {
        return $this->NETCURL_CODE;
    }

    public function getParsed()
    {
        return $this->NETCURL_PARSED;
    }

    public function getUrl()
    {
        $this->NETCURL_URL;
    }

    public function getIp()
    {
        return $this->NETCURL_IP;
    }
}
