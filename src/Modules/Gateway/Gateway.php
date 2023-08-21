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
use Throwable;

use function is_array;

/**
 * Implementation of Resurs Bank gateway.
 */
class Gateway
{
    /**
     * Executes in admin.
     */
    public static function initAdmin(): void
    {
        if (Admin::isSection(sectionName: RESURSBANK_MODULE_PREFIX)) {
            Route::redirectToSettings();
        }

        // Register event listeners to append our gateway.
        add_filter(
            'woocommerce_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::addGateway'
        );
    }

    /**
     * Executes on frontend.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initFrontend(): void
    {
        add_filter(
            'woocommerce_available_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::addPaymentMethods'
        );
        add_filter(
            'woocommerce_gateway_icon',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::modifyIcon',
            10,
            1
        );
        // Filter to fetch Resurs Bank payment methods unvalidated.
        add_filter(
            'resursbank_available_payment_gateways',
            'Resursbank\Woocommerce\Modules\Gateway\Gateway::getResursPaymentMethods'
        );
    }

    /**
     * @return array
     */
    public static function getResursPaymentMethods(): array
    {
        return (array)self::addPaymentMethods(
            gateways: [],
            validateAvailable: false
        );
    }

    /**
     * Add Resurs Bank payment gateway to list of payment methods in admin.
     */
    public static function addGateway(mixed $gateways): mixed
    {
        if (is_array(value: $gateways)) {
            $gateways[] = Resursbank::class;
        }

        return $gateways;
    }

    /**
     * Add Resurs Bank payment methods to list of available methods in checkout.
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
