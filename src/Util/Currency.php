<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * Wrapper method collection for WooCommerce's built-in currency methods
 */
class Currency
{
    /**
     * Simple wrapper for get_woocommerce_currency_symbol to ensure we always get a string (even it is empty).
     * @return string
     */
    public static function getWooCommerceCurrencySymbol(): string
    {
        $currencySymbol = get_woocommerce_currency_symbol();

        if (!is_string(value: $currencySymbol)) {
            return '';
        }

        return $currencySymbol;
    }

    /**
     * Wrapper for get_woocommerce_price_format to ensure we always get a string
     *
     * @return string Defaults to "[symbol] [price]" if return value from WC is not a string
     */
    public static function getWooCommerceCurrencyFormat(): string
    {
        $currencyFormat = get_woocommerce_price_format();

        if (!is_string(value: $currencyFormat)) {
            return '%1$s&nbsp;%2$s';
        }

        return $currencyFormat;
    }
}
