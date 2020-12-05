<?php

namespace TorneLIB;

/**
 * Class NETCURL_NETWORK_DRIVERS Obsolete.
 *
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0.0
 * @deprecated Deprecated class. Do not use.
 */
abstract class NETCURL_NETWORK_DRIVERS
{
    /**
     * @deprecated
     */
    const DRIVER_NOT_SET = 0;
    /**
     * @deprecated
     */
    const DRIVER_CURL = 1;
    /**
     * @deprecated
     */
    const DRIVER_WORDPRESS = 1000;
    /**
     * @deprecated
     */
    const DRIVER_GUZZLEHTTP = 1001;
    /**
     * @deprecated
     */
    const DRIVER_GUZZLEHTTP_STREAM = 1002;

    /**
     * @deprecated Internal driver should be named DRIVER_CURL
     */
    const DRIVER_INTERNAL = 1;
    const DRIVER_SOAPCLIENT = 2;

    /** @var int Using the class itself */
    const DRIVER_OWN_EXTERNAL = 100;

}
