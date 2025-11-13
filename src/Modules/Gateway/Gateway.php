<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Resursbank\Ecom\Lib\Locale\Location;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\CustomerType;
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

        // Filter payment method gateways availability in legacy checkout.
        //
        // Legacy checkout will not filter methods using JavaScript code, it
        // must be done here on backend using this filter.
        //
        // Note that, if you enter the legacy checkout page without it being
        // configured as the WooCommerce checkout page, then this filter is
        // still skipped, as it should be.
        add_filter('woocommerce_available_payment_gateways', function($gateways) {
            // If you are not on the checkout page, or if you are using the
            // blocks based checkout, skip filtering.
            if (!is_checkout() || WooCommerce::isUsingBlocksCheckout()) {
                return $gateways;
            }

            // Resolve total from cart.
            $cartTotal = (float)WC()->cart?->get_total(context: 'edit');

            // Resolve location from customer address.
            $location = Location::tryFrom(value: (string)WC()->customer?->get_billing_country());

            // Determine customer type.
            $customerType = CustomerType::NATURAL;

            // Attempt to resolve company name attached to billing address
            // from POST data. When you change certain fields in the billing
            // form in checkout, street address, country, postal code or city,
            // the checkout gets automatically updated, and these values are
            // picked up from POST data dn set on the customer billing object
            // kept in session. This is why we can pick up country like we do
            // above, without extracting it from the POST data.
            //
            // Since we want to filter payment methods based on company name
            // field value however, we must pick it up directly from the POST
            // data. Because even though it's included, WooCommerce will not
            // update the value on WC()->customer->get_billing_company() until
            // the checkout form is submitted.
            try {
                if (isset($_POST['post_data'])) {
                    parse_str($_POST['post_data'], $data);
                    $billing_company = $data['billing_company'] ?? '';

                    if ((string) $billing_company !== '') {
                        $customerType = CustomerType::LEGAL;
                    }
                }
            } catch (Throwable $error) {
                Log::error(error: $error);
            }

            // Loop through gateways and filter only our Resursbank instances.
            foreach ($gateways as $id => $gateway) {
                if (!($gateway instanceof Resursbank)) {
                    continue;
                }

                // Check if payment method is available.
                try {
                    if (!$gateway->method->isAvailable(
                        amount: $cartTotal,
                        location: $location,
                        customerType: $customerType
                    )) {
                        unset($gateways[$id]);
                    }
                } catch (Throwable $error) {
                    Log::error(error: $error);

                    // Cannot be certain the method should be available, so
                    // filter it.
                    unset($gateways[$id]);
                }
            }

            return $gateways;
        });

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
        if (gettype(value: $icon) !== 'string' || $icon === '') {
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
