<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Module\Store\Repository;
use Throwable;
use WP_Post;

use function in_array;

/**
 * General methods relating to Woocommerce.
 */
class WooCommerce
{
    private static ?string $storeCountry = null;

    /**
     * Safely confirm whether WC is loaded.
     */
    public static function isAvailable(): bool
    {
        return in_array(
            needle: 'woocommerce/woocommerce.php',
            haystack: apply_filters(
                'active_plugins',
                get_option(option: 'active_plugins')
            ),
            strict: true
        );
    }

    /**
     * Trying to determine if the checkout is using blocks or not.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function isUsingBlocksCheckout(): bool
    {
        global $wp_query, $post;

        $blocksCheckoutPageId = wc_get_page_id('checkout');

        // Special legacy vs blocks control
        if ($wp_query !== null && function_exists('get_queried_object')) {
            $post = get_queried_object();
            $currentPostID = (int)($post instanceof WP_Post ? $post->ID : 0);

            // We usually check if the page contains WC blocks, but if we are on the checkout page,
            // but in legacy, we should check blocks based on the post id instead of the preconfigured
            // template.
            if ($currentPostID !== $blocksCheckoutPageId) {
                return has_block('woocommerce/checkout', $currentPostID);
            }
        }

        if ($blocksCheckoutPageId === 0) {
            return false;
        }

        return has_block('woocommerce/checkout', $blocksCheckoutPageId);
    }

    /**
     * Fast way to get a cart total from WC.
     */
    public static function getCartTotals(): float
    {
        return (float)(WC()->cart?->get_totals()['total'] ?? 0.0);
    }

    public static function getRenderedWithNoCrLf(string $content): string
    {
        return $content;
        return preg_replace(
            pattern: '/\n\s*\n/m',
            replacement: " ",
            subject: $content
        );
    }

    /**
     * Return country as string, by the value returned from the current set store.
     *
     * @throws ConfigException
     */
    public static function getStoreCountry(): string
    {
        // Performance fix for moments where this method are recalled several times.
        if (self::$storeCountry !== null) {
            return self::$storeCountry;
        }

        try {
            $configuredStore = Repository::getConfiguredStore();

            if ($configuredStore?->countryCode?->value) {
                self::$storeCountry = strtoupper(
                    string: $configuredStore->countryCode->value
                );
            } else {
                self::$storeCountry = 'EN';
            }
        } catch (Throwable $exception) {
            Config::getLogger()->debug(
                message: 'Store country code fallback to EN. Could be configured: ' . $exception->getMessage()
            );
            self::$storeCountry = 'EN';
        }

        return self::$storeCountry;
    }

    /**
     * Check if WooCommerce supports HPOS or not, and if it is enabled.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function isUsingHpos(): bool
    {
        try {
            // Throws exceptions on nonexistent classes,
            $return = wc_get_container()->get(
                'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController'
            )->custom_orders_table_usage_is_enabled();
        } catch (Throwable) {
            $return = false;
        }

        return $return;
    }

    /**
     * Retrieves the version of a specified asset from its associated .asset.php file.
     *
     * @throws FilesystemException
     * @throws EmptyValueException
     */
    public static function getAssetVersion(string $assetFile = 'gateway'): string
    {
        // Sanitize the input to allow only alphanumeric characters, underscores, and dashes.
        $sanitizedFile = preg_replace('/[^a-zA-Z0-9_-]/', '', $assetFile);

        // Construct the file path.
        $filePath = RESURSBANK_MODULE_DIR_PATH . '/assets/js/dist/' . $sanitizedFile . '.asset.php';

        // Verify the file exists and is within the expected directory.
        if (
            !file_exists(filename: $filePath) ||
            !is_readable(filename: $filePath)
        ) {
            throw new FilesystemException(
                message: "Asset file not found or inaccessible: $filePath"
            );
        }

        // Include the asset file safely.
        $assets = include $filePath;

        // Check if version exists and is valid.
        if (empty($assets['version'])) {
            throw new EmptyValueException(
                message: "Version not found or empty in asset file: $filePath"
            );
        }

        // Return the version if available; otherwise, return an empty string.
        return $assets['version'];
    }

    /**
     * Do a control whether we are in the manual order creation tool or not.
     * HPOS/Legacy friendly.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isAdminOrderCreateTool(): bool
    {
        return Admin::isAdmin() && (
                (
                    self::isUsingHpos() &&
                    isset($_GET['page'], $_GET['action']) &&
                    $_GET['page'] === 'wc-orders' && $_GET['action'] === 'new'
                ) ||
                (
                    !self::isUsingHpos() &&
                    isset($_GET['post_type'], $_GET['action']) &&
                    $_GET['post_type'] === 'shop_order' &&
                    $_GET['action'] === 'add'
                )
            );
    }
}
