<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Session;

use Resursbank\Ecom\Lib\Session\Session as BaseSession;
use Resursbank\Ecom\Lib\Session\SessionHandlerInterface;
use RuntimeException;
use WC_Session_Handler;
use WooCommerce;

class Session extends BaseSession implements SessionHandlerInterface
{
    public function set(string $key, ?string $val): void
    {
        WC()->session->set(key: $key, value: $val);
    }

    public function get(string $key): string
    {
        return (string) WC()->session->get(key: $this->getKey(key: $key));
    }

    public function delete(string $key): void
    {
        // No delete method in WC_Session_Handler, so we set to null.
        $this->set(key: $key, val: null);
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

        if (!function_exists(function: 'WC')) {
            return;
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
