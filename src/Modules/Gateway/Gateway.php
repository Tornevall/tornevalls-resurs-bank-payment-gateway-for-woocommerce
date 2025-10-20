<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use JsonException;
use ReflectionException;
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
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
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
