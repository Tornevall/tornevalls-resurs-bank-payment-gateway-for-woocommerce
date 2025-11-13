<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

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
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Order;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Modules\Store\Store;
use Resursbank\Woocommerce\Settings\Filter\InvalidateCacheButton;
use Resursbank\Woocommerce\Settings\Filter\TestCallbackButton;
use Resursbank\Woocommerce\Settings\Settings;
use Throwable;

/**
 * Module initialization class for functionality used by wp-admin.
 */
class Admin
{
    /**
     * Init various modules.
     *
     * @throws ConfigException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws Throwable
     * @todo The enable check can be moved to the init.php file instead, so we do not need it in the Frontend init, the Admin init and the Shared init.
     */
    public static function init(): void
    {
        // Settings-related init methods that need to run in order for the plugin to be configurable when
        // it's inactivated.
        Settings::init();
        InvalidateCacheButton::init();
        TestCallbackButton::init();
        PartPayment::initAdmin();
        Store::initAdmin();

        if (!Repository::isEnabled(field: Field::ENABLED)) {
            return;
        }

        // Initialize same block components for Admin as for the frontend to mark
        // payment methods as compatible in block editor.
        GatewayBlocks::init();
        Gateway::initAdmin();
        Order::init();
        OrderManagement::init();
        PaymentInformation::init();
        Order::initAdmin();

        // Hide payment methods from the payment gateways list in WooCommerce
        // settings. There is a hook available
        // (woocommerce_admin_field_payment_gateways) which executes where
        // payment methods are rendered. However, the hook executes, the page
        // renders, and payment gateways are then fetched using an AJAX call.
        // The AJAX call also maks the request from us seeing that it executes
        // in the administration panel.
        //
        // As such, there is no good way to safely filter out our payment
        // methods from the list specifically displayed inside the admin panel
        // instead we use this work-around to ensure that our methods are
        // always present, but just hidden where they are not desired.
        add_action('admin_head', function () {
            $selectors = [];

            /** @var PaymentMethod $method */
            foreach (PaymentMethodRepository::getPaymentMethods() as $method) {
                $id = $method->id;

                // Escape first char if it's a digit, otherwise the CSS
                // selector will be invalid. For example, "123gateway" becomes
                // "\31 23gateway".
                if (preg_match(pattern: '/^[0-9]/', subject: $id)) {
                    $id = '\\3' . substr(string: $id, offset: 0, length: 1) . ' ' . substr(string: $id, offset: 1);
                }

                $selectors[] = ".settings-payment-gateways #{$id}";
            }

            echo '<style>' . implode(separator: ', ', array: $selectors) . ' { display:none !important; }</style>';
        });
    }
}
