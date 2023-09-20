<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
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
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
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
        if (isset($fields['billing']) && is_array(value: $fields['billing'])) {
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
        // Making sure that cache-less solution only fetches payment methods once and reusing
        // data if already fetched during a single threaded call.
        global $paymentMethodList;

        if (!is_array(value: $gateways)) {
            return $gateways;
        }

        try {
            if (!$paymentMethodList instanceof PaymentMethodCollection) {
                $paymentMethodList = PaymentMethodRepository::getPaymentMethods(
                    storeId: StoreId::getData()
                );
            }

            foreach ($paymentMethodList as $paymentMethod) {
                $gateway = new Resursbank(method: $paymentMethod);

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
}
