<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\ForcePaymentMethodSortOrder;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use function is_array;

/**
 * Implementation of Resurs Bank gateway.
 */
class Gateway
{
    /**
     * Add payment gateways.
     */
    public static function init(): void
    {
        add_filter(
            'woocommerce_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::addPaymentMethods'
        );

        // Ensure that if you make any changes below this point, you handle the sorting
        // requirement correctly.
        if (!ForcePaymentMethodSortOrder::getData()) {
            return;
        }

        // Perform a verification process for sorting after WooCommerce has initialized
        // the payment gateways. If forced sorting is disabled, this will never occur.
        // The Feature is only available from WooCommerce 8.5.0 and above.
        add_action(
            'wc_payment_gateways_initialized',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::handleInitializedGatewaysSorting'
        );

        add_filter(
            'woocommerce_available_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::getAvailablePaymentGatewaysSorted'
        );
    }

    /**
     * @param $wcPaymentGateways
     * @return void
     * @throws ConfigException
     */
    public static function handleInitializedGatewaysSorting($wcPaymentGateways): void
    {
        try {
            // Check if there's an object to handle instead of an instance of
            // WC_Payment_Gateways, as the Gateway may execute before other
            // initializations before WC. We also want to make sure that
            // there are gateways available. This validation prevents someone
            // handing over broken data.
            if (!is_object(value: $wcPaymentGateways) ||
                !property_exists(object_or_class: $wcPaymentGateways, property: 'payment_gateways') ||
                !is_array(value: $wcPaymentGateways->payment_gateways) ||
                !(new ArrayValidation())->isAssoc(data: $wcPaymentGateways->payment_gateways)
            ) {
                Config::getLogger()->debug(message: 'Handle initialized gateways sorting could not find a valid array.');
                return;
            }
        } catch (Throwable $e) {
            Config::getLogger()->error(message: $e);
            return;
        }

        // This call fixes payment gateway sorting immediately after
        // initialization, when the Resurs gateway is placed on position 999 or
        // higher and forced sorting is enabled.
        $wcPaymentGateways->payment_gateways = self::getAvailablePaymentGatewaysSorted(
            availableGateways: $wcPaymentGateways->payment_gateways
        );
    }

    /**
     * Adjust sort order for payment gateways. Can be used both on available/active gateways and all installed gateways.
     *
     * @param array $availableGateways
     * @return array
     * @throws ConfigException
     */
    // @phpcs:ignoreFile CognitiveComplexity
    public static function getAvailablePaymentGatewaysSorted(array $availableGateways = []): array
    {
        $ordering = (array)get_option('woocommerce_gateway_order');

        if (!isset($ordering['resursbank'])) {
            // If this is not set, woocommerce currently has no sort order for Resurs, so we will
            // place the payment methods in top order initially.
            $ordering['resursbank'] = 0;
        }

        $sortGateways = [];
        $ourId = -1;
        $hasId = false;

        foreach ($availableGateways as $id => $gateway) {
            if (!isset($ordering[$id]) && !($gateway instanceof Resursbank)) {
                continue;
            }

            if ($gateway->id === 'resursbank') {
                $ourId = $id;
                $hasId = true;
            }

            $sort = $gateway instanceof Resursbank
                ? $ordering['resursbank'] . '_' . $gateway->sortOrder . '_' . $id
                : $ordering[$id];
            $sortGateways[$sort] = $gateway;
        }

        ksort(array: $sortGateways, flags: SORT_NUMERIC);

        $backupAvailableGateways = $availableGateways;
        $availableGateways = [];

        // Create new sort order.
        foreach ($sortGateways as $gateway) {
            $availableGateways[$gateway->id] = $gateway;
        }

        // If something breaks, restore the original list.
        if (count($availableGateways) !== count($backupAvailableGateways)) {
            $availableGateways = $backupAvailableGateways;
        }

        try {
            // When our module is newly installed, it is assigned a sort order of 999 after
            // the initialization of the wc-gateway (wc_payment_gateways_initialized).
            // This also means that the variable $ordering['resursbank'] was not properly
            // initialized within this method, but was forced to have an order of 0.
            // When this occurs, the wp-admin/payments tab arranges our module's payment
            // gateway list at the end of the configuration, but at the top during the
            // checkout process. This section of code is intended to adjust the sort order
            // both in wp-admin and during the checkout process.
            if (
                (int)$ourId >= 999 &&
                count($availableGateways) &&
                $hasId
            ) {
                // Create a temporary array containing our module's gateway at position 999
                $resursArray = [$availableGateways[$ourId]];

                // Remove our module's gateway from position 999 in the original list
                unset($availableGateways[$ourId]);

                // Merge the temporary array containing our module's gateway with the original list
                $availableGateways = array_merge($resursArray, $availableGateways);

                Config::getLogger()->debug(message: 'Resurs gateway sort id found and rearranged.');
            }
        } catch (Throwable $e) {
            Config::getLogger()->error(message: $e);
        }

        return $availableGateways;
    }

    /**
     * Executes in admin.
     */
    public static function initAdmin(): void
    {
        if (!Admin::isSection(sectionName: RESURSBANK_MODULE_PREFIX)) {
            return;
        }

        Route::redirectToSettings();
    }

    /**
     * Executes on frontend.
     */
    public static function initFrontend(): void
    {
        add_filter(
            'woocommerce_gateway_icon',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::modifyIcon',
            10,
            1
        );
    }

    /**
     * Add Resurs Bank payment methods to the list of available methods in checkout.
     *
     * @param mixed $gateways Preferably an array with gateways but given as a mixed from WP/WC.
     * @param bool $validateAvailable Ignored during normal filters. Use from a secondary will skip some validations.
     */
    public static function addPaymentMethods(mixed $gateways, bool $validateAvailable = true): mixed
    {
        if (!is_array(value: $gateways) || WooCommerce::isAdminOrderCreateTool()) {
            return $gateways;
        }

        try {
            $paymentMethodList = self::getPaymentMethodList();

            // Handle internal sort order by the order we get payment methods
            // from the API.
            $sortOrder = 0;

            foreach ($paymentMethodList as $paymentMethod) {
                $sortOrder++;

                $gateway = new Resursbank(
                    method: $paymentMethod,
                    sortOrder: $sortOrder
                );

                if ($validateAvailable && !$gateway->is_available()) {
                    continue;
                }

                $gateways[$paymentMethod->id] = $gateway;
            }
        } catch (Throwable $e) {
            Log::error(error: $e);

            /**
             * @todo Consider an alternative method for displaying errors.
             *
             * The messages below will always appear on the screen, even if no credentials are set.
             * The primary intent is to display errors to admin users, such as 502 Gateway Errors.
             * However, the error code for such messages is 0, making it difficult to track them properly.
             */
            //if (Admin::isAdmin()) {
            //    Log::error(error: $e, message: 'A problem occurred when fetching payment methods.');
            //}
        }

        // Add the default method to payment gateways.
        // Will only be reflected on gateway page, see \Resursbank\Woocommerce\Modules\Gateway\Resursbank::is_available
        $gateways[] = Resursbank::class;

        return $gateways;
    }

    /**
     * Apply styling to payment method icons.
     */
    public static function modifyIcon(mixed $icon): mixed
    {
        if (gettype($icon) !== 'string' || $icon === '') {
            return $icon;
        }

        return preg_replace(
            pattern: '/>$/',
            replacement: ' style="padding:0;margin:0;max-height:1em;vertical-align:middle;' . apply_filters(
                'resursbank_icon_float',
                'float:right;'
            ) . '">',
            subject: $icon
        );
    }

    /**
     * @return PaymentMethodCollection
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     */
    public static function getPaymentMethodList(): PaymentMethodCollection
    {
        // Making sure that cache-less solution only fetches payment methods once and reusing
        // data if already fetched during a single threaded call.
        global $paymentMethodList;

        if (!$paymentMethodList instanceof PaymentMethodCollection) {
            $paymentMethodList = PaymentMethodRepository::getPaymentMethods();
        }

        return $paymentMethodList;
    }
}
