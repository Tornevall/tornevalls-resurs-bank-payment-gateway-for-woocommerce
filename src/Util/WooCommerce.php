<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Config;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Throwable;

use function in_array;

/**
 * General methods relating to Woocommerce.
 */
class WooCommerce
{
    /**
     * Safely confirm whether WC is loaded.
     */
    public static function isAvailable(): bool
    {
        return in_array(
            needle: 'woocommerce/woocommerce.php',
            haystack: apply_filters(
                hook_name: 'active_plugins',
                value: get_option('active_plugins')
            ),
            strict: true
        );
    }

    /**
     * Verify that the plugin has a valid setup ready.
     */
    public static function isValidSetup(): bool
    {
        try {
            return Config::hasInstance() && ClientId::getData() !== '' && ClientSecret::getData() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public static function getEcomLocale(string $countryLocale): string
    {
        return match (strtolower(string: $countryLocale)) {
            'se' => 'sv',
            'dk' => 'da',
            'nb', 'nn' => 'no',
            default => $countryLocale
        };
    }
}
