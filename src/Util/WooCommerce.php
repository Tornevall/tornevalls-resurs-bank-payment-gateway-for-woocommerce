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

    /**
     * Fast way to get a cart total from WC.
     */
    public static function getCartTotals(): float
    {
        return (float)(WC()->cart?->get_totals()['total'] ?? 0.0);
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

    /**
     * Check if WooCommerce supports HPOS or not, and if it is enabled.
     */
    public static function isUsingHpos(): bool
    {
        try {
            // Throws exceptions on unexistent classes,
            $return = wc_get_container()->get(
                'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController'
            )->custom_orders_table_usage_is_enabled();
        } catch (Throwable) {
            $return = false;
        }

        return $return;
    }

    /**
     * Do a control whether we are in the manual order creation tool or not.
     * HPOS/Legacy friendly.
     */
    public static function isAdminOrderCreateTool(): bool
    {
        return Admin::isAdmin() && (
                (self::isUsingHpos() && isset($_GET['page'], $_GET['action']) && $_GET['page'] === 'wc-orders' && $_GET['action'] === 'new') ||
                (!self::isUsingHpos() && isset($_GET['post_type'], $_GET['action']) && $_GET['post_type'] === 'shop_order' && $_GET['action'] === 'add')
            );
    }
}
