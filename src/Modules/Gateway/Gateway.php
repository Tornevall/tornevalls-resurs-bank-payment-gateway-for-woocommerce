<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\ForcePaymentMethodSortOrder;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
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

        // If you do something below this place, also make sure you handle the forced
        // sorting correctly.
        if (!ForcePaymentMethodSortOrder::getData()) {
            return;
        }

        add_filter(
            'woocommerce_available_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::getAvailablePaymentGatewaysSorted'
        );
    }

    /**
     * @param array $availableGateways
     * @return array
     */
    public static function getAvailablePaymentGatewaysSorted(array $availableGateways = []): array
    {
        $ordering = (array)get_option('woocommerce_gateway_order');
        if (!isset($ordering['resursbank'])) {
            // If this is not set, woocommerce currently has no sort order for Resurs, so we will
            // place the payment methods in top order initially.
            $ordering['resursbank'] = 0;
        }

        $sortGateways = [];

        foreach ($availableGateways as $id => $gateway) {
            if (!isset($ordering[$id]) && !($gateway instanceof Resursbank)) {
                continue;
            }

            $sort = $gateway instanceof Resursbank
                ? $ordering['resursbank'] . '_' . $gateway->sortOrder . '_' . $id
                : $ordering[$id];
            $sortGateways[$sort] = $gateway;
        }

        ksort(array: $sortGateways, flags: SORT_NUMERIC & SORT_NATURAL);

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
        add_filter(
            'woocommerce_checkout_fields',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::checkoutFieldHandler'
        );
    }

    /**
     * Add custom field as helper to company payments.
     *
     * @param null $fields Nullable. Fields may not necessarily be received properly initially.
     * @return array|null
     */
    public static function checkoutFieldHandler($fields = null): ?array
    {
        // Validate that we really got the fields properly.
        if (
            self::hasPaymentMethodsLegal() &&
            isset($fields['billing']) &&
            is_array(value: $fields['billing'])
        ) {
            $fields['billing']['billing_resurs_government_id'] = [
                'label' => Translator::translate(
                    phraseId: 'customer-type-legal'
                ),
                'class' => '',
                'required' => false,
                'priority' => 31,
            ];
        }

        return $fields;
    }

    /**
     * Add Resurs Bank payment methods to list of available methods in checkout.
     *
     * @param mixed $gateways Preferably an array with gateways but given as a mixed from WP/WC.
     * @param bool $validateAvailable Ignored during normal filters. Use from a secondary will skip some validations.
     */
    public static function addPaymentMethods(mixed $gateways, bool $validateAvailable = true): mixed
    {
        if (!is_array(value: $gateways)) {
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
        }

        // Add default method to payment gateways. Will only be reflected on
        // gateway page, see \Resursbank\Woocommerce\Modules\Gateway\Resursbank::is_available
        $gateways[] = Resursbank::class;

        return $gateways;
    }

    /**
     * Apply styling to payment method icons.
     */
    public static function modifyIcon(mixed $icon): string
    {
        if ($icon === '') {
            return $icon;
        }

        return preg_replace(
            pattern: '/>$/',
            replacement: ' style="padding:0;margin:0;max-height:1em;vertical-align:middle;">',
            subject: $icon
        );
    }

    /**
     * Returns a boolean value if payment method collection has LEGAL customer support.
     */
    private static function hasPaymentMethodsLegal(): bool
    {
        try {
            $paymentMethodList = self::getPaymentMethodList();

            if ($paymentMethodList->count()) {
                /** @var Payment\PaymentMethod $paymentMethod */
                foreach ($paymentMethodList as $paymentMethod) {
                    if ($paymentMethod->enabledForLegalCustomer) {
                        $return = true;
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        return $return ?? false;
    }

    /**
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws AuthException
     * @throws CurlException
     * @throws IllegalValueException
     * @throws \JsonException
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws \ReflectionException
     * @throws ApiException
     * @throws CacheException
     */
    private static function getPaymentMethodList(): PaymentMethodCollection
    {
        // Making sure that cache-less solution only fetches payment methods once and reusing
        // data if already fetched during a single threaded call.
        global $paymentMethodList;

        if (!$paymentMethodList instanceof PaymentMethodCollection) {
            $paymentMethodList = PaymentMethodRepository::getPaymentMethods(
                storeId: StoreId::getData()
            );
        }

        return $paymentMethodList;
    }
}
