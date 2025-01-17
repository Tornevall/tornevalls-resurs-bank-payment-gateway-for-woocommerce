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
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
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
        return has_block('woocommerce/checkout', wc_get_page_id('checkout'));
    }

    /**
     * Fast way to get a cart total from WC.
     */
    public static function getCartTotals(): float
    {
        return (float)(WC()->cart?->get_totals()['total'] ?? 0.0);
    }

    /**
     * Return country as string, by the value returned from the current set store.
     *
     * @throws ConfigException
     */
    public static function getStoreCountry(): string
    {
        $return = 'EN';

        try {
            if (StoreId::getData() !== '') {
                $return = strtoupper(
                    string: Repository::getConfiguredStore()?->countryCode->value
                ) ?? 'EN';
            }
        } catch (Throwable $exception) {
            Config::getLogger()->debug(
                message: 'Store country code fallback to EN. Could be configured: ' . $exception->getMessage()
            );
        }

        return $return;
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
