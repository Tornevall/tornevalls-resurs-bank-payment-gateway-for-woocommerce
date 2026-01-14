<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use JsonException;
use ReflectionException;
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
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\AnnuityFactor\Repository as AnnuityRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
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
     * Is WooCommerce present?
     */
    public static function isWcPresent(): bool
    {
        return class_exists(
            class: 'Automattic\WooCommerce\Utilities\FeaturesUtil'
        );
    }

    /**
     * Trying to determine if the checkout is using blocks or not.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function isUsingBlocksCheckout(): bool
    {
        $checkoutPageId = (int) wc_get_page_id('checkout');

        if ($checkoutPageId > 0) {
            return has_block('woocommerce/checkout', $checkoutPageId);
        }

        return false;
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
     * Retrieves the version of a specified asset from its associated .asset.php file.
     *
     * @throws FilesystemException
     * @throws EmptyValueException
     */
    public static function getAssetVersion(string $assetFile = 'gateway'): string
    {
        // Sanitize the input to allow only alphanumeric characters, underscores, and dashes.
        $sanitizedFile = preg_replace(
            pattern: '/[^a-zA-Z0-9_-]/',
            replacement: '',
            subject: $assetFile
        );

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
     * @todo Should be removed, if anything is to be kept it should be moved to Ecom.
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

        $currentPaymentMethod = UserSettingsRepository::getSettings()->partPaymentMethodId;

        // Only switch this if changes has been made.
        if ($currentPaymentMethod === $paymentMethodId) {
            return;
        }

        update_option(
            Reader::getOptionName(field: Field::PART_PAYMENT_METHOD_ID),
            $paymentMethodId
        );

        if (UserSettingsRepository::getSettings()->partPaymentPeriod !== null) {
            return;
        }

        update_option(Reader::getOptionName(field: Field::PART_PAYMENT_PERIOD), $longestPeriod);
    }

    /**
     * Check for misconfigured payment methods during CSS-process and option updates in wp-admin.
     *
     * @todo Should be removed.
     */
    public static function validateAndUpdatePartPaymentMethod(): bool
    {
        $paymentMethod = null;

        try {
            $paymentMethod = UserSettingsRepository::getPartPaymentMethod();

            // Failsafe: If there is no payment method set, which usually is the case even though the widget is not enabled
            // itself, it has to be preconfigured. In Settings/PartPayment this is very much handled when credentials
            // are saved. This could potentially occur when switching stores as WordPress saving functions are delayed.
            if (
                !$paymentMethod instanceof EcomPaymentMethod &&
                UserSettingsRepository::hasUserCredentials()
            ) {
                WooCommerce::updatePartPaymentData(
                    paymentMethods: Repository::getPaymentMethods()
                );
            }
        } catch (Throwable) {
        }

        return $paymentMethod instanceof EcomPaymentMethod;
    }

    /**
     * Type-safe wrapper for wc_get_order_status_name.
     */
    public static function getOrderStatusName(string $status): string
    {
        $name = wc_get_order_status_name(status: $status);

        if (!is_string(value: $name)) {
            return $status;
        }

        return $name;
    }

    /**
     * Strip 'wc-' prefix from status string.
     */
    public static function stripStatusPrefix(
        string $status
    ): string {
        $result = $status;

        if (
            strlen(string: $result) > 3 &&
            str_starts_with(haystack: $status, needle: 'wc-')
        ) {
            $result = substr(string: $status, offset: 3);
        }

        return $result;
    }
}
