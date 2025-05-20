<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethod as EcomPaymentMethod;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Module\AnnuityFactor\Repository as AnnuityRepository;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Modules\Api\Connection;
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
     * Is WooCommerce present?
     */
    public static function isWcPresent(): bool
    {
        return class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil');
    }

    /**
     * Trying to determine if the checkout is using blocks or not.
     *
     * NOTE: This function also checks whether the current page is the checkout
     * page.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function isUsingBlocksCheckout(): bool
    {
        global $wp_query, $post;

        $blocksCheckoutPageId = wc_get_page_id('checkout');

        // Special legacy vs blocks control
        if ($wp_query !== null && function_exists('get_queried_object')) {
            $objectId = function_exists('get_queried_object_id')
                ? get_queried_object_id()
                : 0;
            $post = get_queried_object();
            $currentPostID = (int)($post instanceof WP_Post ? $post->ID : $objectId);

            // We usually check if the page contains WC blocks, but if we are on the checkout page,
            // but in legacy, we should check blocks based on the post id instead of the preconfigured
            // template.
            //
            // For WP 6.8 this check breaks on special occasions where the current post id remains 0.
            // We should, however, avoid changing the business logic for this method and proceed as usual
            // if this happens.
            //
            // See https://resursbankplugins.atlassian.net/browse/WOO-1455 for the issue where this was first
            // discovered.
            // Issue should be solved in the address handling segment instead.
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

    /**
     * Render content with CR/LF removed (for unsupported themes).
     */
    public static function getRenderedWithNoCrLf(string $content): string
    {
        return preg_replace(
            pattern: '/\n\s*\n/m',
            replacement: ' ',
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
     * Full cache invalidation.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function invalidateFullCache(): void
    {
        global $wpdb;

        try {
            /** @noinspection SqlNoDataSourceInspection */
            $transients = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options}
                         WHERE option_name LIKE '_transient_resurs%'"
            );

            // Making sure we delete other cached transients as well, besides the ecom cache.
            foreach ($transients as $transient) {
                $transient_name = str_replace('_transient_', '', $transient);
                delete_transient($transient_name);
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
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
     * @throws ConfigException
     * @throws EmptyValueException
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function updatePartPaymentData(PaymentMethodCollection $paymentMethods): void
    {
        $longestPeriod = 0;
        $paymentMethodId = '';
        $firstFilteredMethod = AnnuityRepository::filterMethods(
            paymentMethods: $paymentMethods
        )->getFirst();
        $annuityFactors = AnnuityRepository::getAnnuityFactors(
            paymentMethodId: $firstFilteredMethod->id
        );

        if ($annuityFactors->count() > 0) {
            $paymentMethodId = $firstFilteredMethod->id;

            foreach ($annuityFactors as $annuityFactor) {
                if ($annuityFactor->interest > 0.0) {
                    continue;
                }

                $longestPeriod = max(
                    $annuityFactor->durationMonths,
                    $longestPeriod
                );
            }
        }

        if ($longestPeriod <= 0) {
            return;
        }

        $currentPaymentMethod = PaymentMethod::getData();

        // Only switch this if changes has been made.
        if ($currentPaymentMethod === $paymentMethodId) {
            return;
        }

        update_option(PaymentMethod::getName(), $paymentMethodId);

        if (Period::getData()) {
            return;
        }

        update_option(Period::getName(), $longestPeriod);
    }

    /**
     * Check for misconfigured payment methods during CSS-process and option updates in wp-admin.
     */
    public static function validateAndUpdatePartPaymentMethod(): bool
    {
        $paymentMethod = null;

        try {
            $paymentMethodSet = PaymentMethod::getData();
            $paymentMethod = \Resursbank\Ecom\Module\PaymentMethod\Repository::getById(
                paymentMethodId: $paymentMethodSet
            );

            // Failsafe: If there is no payment method set, which usually is the case even though the widget is not enabled
            // itself, it has to be preconfigured. In Settings/PartPayment this is very much handled when credentials
            // are saved. This could potentially occur when switching stores as WordPress saving functions are delayed.
            if (
                !$paymentMethod instanceof EcomPaymentMethod &&
                Connection::hasCredentials()
            ) {
                WooCommerce::updatePartPaymentData(
                    paymentMethods: \Resursbank\Ecom\Module\PaymentMethod\Repository::getPaymentMethods()
                );
            }
        } catch (Throwable) {
        }

        return $paymentMethod instanceof PaymentMethod;
    }
}
