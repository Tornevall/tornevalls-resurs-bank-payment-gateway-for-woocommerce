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
use Throwable;
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
     */
    private static WooCommerce $wooCom;

    /**
     * @param string|null $value Using null to "unset".
     */
    public static function set(string $key, ?string $value): bool
    {
        try {
            self::getWcSession();
            self::$wooCom->session->set(key: $key, value: $value);
            $return = true;
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
            $return = false;
        }

        return $return;
    }

    public static function get(string $key): string
    {
        try {
            self::getWcSession();

            $return = (string)self::$wooCom->session->get(key: $key);
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
        }

        return $return ?? '';
    }

    /**
     * Fetch customer type stored in session.
     */
    public static function getCustomerType(): CustomerType
    {
        $type = self::get(
            key: (new Session())->getKey(
                key: Repository::SESSION_KEY_CUSTOMER_TYPE
            )
        );

        if ($type === '') {
            $type = 'NATURAL';
        }

        try {
            return CustomerType::from(value: $type);
        } catch (Throwable $e) {
            Log::error(error: $e);

            return CustomerType::NATURAL;
        }
    }

    /**
     * Get government ID stored in session.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getGovernmentId(): ?string
    {
        return WC()->session->get(
            (new Session())->getKey(
                key: Repository::SESSION_KEY_SSN_DATA
            )
        );
    }

    /**
     * Unset.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function unset(string $key): void
    {
        try {
            self::getWcSession();

            self::$wooCom->session->set($key, null);
        } catch (RuntimeException) {
            // If WC()->session is not available, we can't use it.
        }
    }

    /**
     * Initializing WC() if not already done.
     */
    private static function getWcSession(): void
    {
        /**
         * Psalm warns about the isset-check, but without this check, we get fatal errors when not set.
         *
         * @psalm-suppress RedundantCondition
         * @psalm-suppress RedundantPropertyInitializationCheck
         */
        if (isset(self::$wooCom) && !(self::$wooCom instanceof WooCommerce)) {
            throw new RuntimeException(
                message: 'WooCommerce is not available.'
            );
        }

        self::$wooCom = WC();
        self::$wooCom->initialize_session();

        /**
         * @psalm-suppress MixedPropertyFetch
         */
        if (!self::$wooCom->session instanceof WC_Session_Handler) {
            throw new RuntimeException(
                message: 'WC()->session is not available.'
            );
        }
    }
}
