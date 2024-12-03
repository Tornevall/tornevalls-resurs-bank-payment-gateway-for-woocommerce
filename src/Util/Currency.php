<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;

use function is_string;

/**
 * Wrapper method collection for WooCommerce's built-in currency methods
 */
class Currency
{
    /**
     * Simple wrapper for get_woocommerce_currency_symbol to ensure we always
     * get a string (even it is empty).
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getWooCommerceCurrencySymbol(): string
    {
        // Feature used by ecom init, for where woocommerce in an early init is not available.
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currencySymbol = get_woocommerce_currency_symbol();
            set_transient('resurs_early_currency_symbol', $currencySymbol);
        } else {
            $currencySymbol = get_transient('resurs_early_currency_symbol');
        }

        return !is_string(value: $currencySymbol) ? '' : $currencySymbol;
    }

    /**
     * Wrapper for get_woocommerce_price_format to ensure we always get a string
     *
     * @return string Defaults to "[symbol] [price]" if return value from WC is not a string
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public static function getWooCommerceCurrencyFormat(): string
    {
        // Feature used by ecom init, for where woocommerce in an early init is not available.
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currencyFormat = get_woocommerce_price_format();
            set_transient('resurs_early_currency_format', $currencyFormat);
        } else {
            $currencyFormat = get_transient('resurs_early_currency_format');
        }

        if (!is_string(value: $currencyFormat)) {
            return '%1$s&nbsp;%2$s';
        }

        return $currencyFormat;
    }

    /**
     * Fetches currency format as an Ecom CurrencyFormat object.
     */
    public static function getEcomCurrencyFormat(): CurrencyFormat
    {
        $wooFormat = self::getWooCommerceCurrencyFormat();

        if (
            preg_match(pattern: '/\%1\$s.*\%2\$s/', subject: $wooFormat)
        ) {
            return CurrencyFormat::SYMBOL_FIRST;
        }

        return CurrencyFormat::SYMBOL_LAST;
    }

    /**
     * Format a number using configured price format and symbol.
     */
    public static function getFormattedAmount(float $amount): string
    {
        $currencySymbol = self::getWooCommerceCurrencySymbol();

        $total = number_format(
            num: $amount,
            decimals: self::getConfiguredDecimalPoints(),
            decimal_separator: ',',
            thousands_separator: ''
        );

        return self::getEcomCurrencyFormat() === CurrencyFormat::SYMBOL_FIRST ?
            $currencySymbol . ' ' . $total : $total . ' ' . $currencySymbol;
    }

    /**
     * Fetch configured number of decimals to use for prices. Will not accept values outside the 0-2 range. If no
     * configured value found the default response is 2.
     */
    public static function getConfiguredDecimalPoints(): int
    {
        $points = get_option('woocommerce_price_num_decimals');

        if ($points === false) {
            return 2;
        }

        if (is_string(value: $points)) {
            $points = (int)$points;
        }

        if ($points < 0) {
            return 0;
        }

        if ($points > 2) {
            return 2;
        }

        return $points;
    }
}
