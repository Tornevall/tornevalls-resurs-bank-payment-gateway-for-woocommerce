<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository;
use RuntimeException;
use WC_Session_Handler;
use WooCommerce;

/**
 * WooCommerce <-> ECom2 Session handling.
 *
 * Since WooCommerce are building their own session handling through cookies, we need to make sure we can use it
 * with ecom.
 */
class WcSession
{
    /**
     * WC() as an initialized variable.
     * @var WooCommerce
     * @psalm-suppress UndefinedClass
     */
    private static WooCommerce $wooCom;

    /**
     * Initializing WC() if not already done.
     * @return void
     */
    private static function getWcSession(): void
    {
        /**
         * @psalm-suppress UndefinedClass
         */
        if (!(self::$wooCom instanceof WooCommerce)) {
            throw new RuntimeException(message: 'WooCommerce is not available.');
        }

        /**
         * @psalm-suppress MixedAssignment
         */
        self::$wooCom = WC();

        /**
         * @psalm-suppress MixedPropertyFetch
         */
        if (isset(self::$wooCom) && (!self::$wooCom->session instanceof WC_Session_Handler)) {
            throw new RuntimeException(message: 'WC()->session is not available.');
        }
    }

    /**
     * @param string $key
     * @param null|string $value Using null to "unset".
     * @return void
     */
    public static function set(string $key, null|string $value): void
    {
        try {
            self::getWcSession();

            /**
             * @psalm-suppress MixedMethodCall
             */
            self::$wooCom->session->set($key, $value);
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
        }
    }

    /**
     * @param string $key
     * @return string
     */
    public static function get(string $key): string
    {
        try {
            self::getWcSession();

            /**
             * @psalm-suppress MixedMethodCall
             */
            $return = (string)self::$wooCom->session->get($key);
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
        }

        return $return ?? '';
    }

    /**
     * @return CustomerType
     */
    public static function getCustomerType(): CustomerType
    {
        return CustomerType::from(self::get((new Session())->getKey(key: Repository::SESSION_KEY_CUSTOMER_TYPE)));
    }

    /**
     * @param string $key
     * @return void
     */
    public static function unset(string $key): void
    {
        try {
            self::getWcSession();

            /**
             * @psalm-suppress MixedMethodCall
             */
            self::$wooCom->session->set($key, null);
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
        }
    }
}
