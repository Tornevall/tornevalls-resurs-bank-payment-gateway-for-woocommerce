<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;

use function is_string;

/**
 * Wrapper method collection for WooCommerce's built-in currency methods
 */
class Currency
{
    /**
     * Simple wrapper for get_woocommerce_currency_symbol to ensure we always
     * get a string (even it is empty).
     */
    public static function getWooCommerceCurrencySymbol(): string
    {
        $currencySymbol = get_woocommerce_currency_symbol();

        return !is_string(value: $currencySymbol) ? '' : $currencySymbol;
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
        $configuredDecimalPoints = get_option(
            option: 'woocommerce_price_num_decimals'
        );

        if ($configuredDecimalPoints === false) {
            return 2;
        }

        if (is_string(value: $configuredDecimalPoints)) {
            $configuredDecimalPoints = (int)$configuredDecimalPoints;
        }

        if ($configuredDecimalPoints < 0) {
            return 0;
        }

        if ($configuredDecimalPoints > 2) {
            return 2;
        }

        return $configuredDecimalPoints;
    }
}
