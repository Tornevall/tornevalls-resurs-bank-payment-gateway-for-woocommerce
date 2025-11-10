<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
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
        // This hook mitigates stale payment ids on orders when customers
        // abandon the checkout and later return to complete the purchase using
        // an alternative payment method not supplied by Resurs Bank.
        //
        // Scenario this fixes:
        //
        // 1. Customer places order, lands on Resurs gateway.
        // 2. Customer uses back button, or address bar etc., to nav back to the checkout page.
        // 3. Checkout is still active in WC session, as is standard.
        // 4. Customer selects a different payment method from a different provider and completes the order.
        // 5. The order now has a payment id from Resurs Bank stored in order metadata, even though the payment was completed using another provider.
        //
        // Customer backtracking and selecting a different payment method from
        // Resurs Bank, effectively creating a new payment, is not an issue,
        // because we update the metadata value when processing the new payment.
        add_action(
            'woocommerce_rest_checkout_process_payment_with_context',
            function (PaymentContext $data) {
                if ($data->get_payment_method_instance() instanceof Resursbank) {
                    return;
                }

                // Clear potentially existing payment ID in order metadata.
                Metadata::setPaymentId(order: $data->order, id: '');
            }
        );

        if (!Repository::isEnabled(field: Field::ENABLED)) {
            return;
        }

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
     */
    public static function addPaymentMethods(mixed $gateways): mixed
    {
        // Ensure our methods are not added in admin order create tool.
        if (!is_array(value: $gateways) || WooCommerce::isAdminOrderCreateTool()) {
            return $gateways;
        }

        try {
            /** @var PaymentMethod $paymentMethod */
            foreach (PaymentMethodRepository::getPaymentMethods() as $paymentMethod) {
                $gateways[$paymentMethod->id]  = new Resursbank(method: $paymentMethod);
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        $gateways[ResursbankLink::ID] = new ResursbankLink();

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
}
