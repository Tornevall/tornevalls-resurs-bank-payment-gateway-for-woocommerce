<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\ModuleInit;

use Resursbankabpayments\Woocommerce\Database\Options\Api\Enabled;
use Resursbankabpayments\Woocommerce\Modules\Gateway\Gateway;
use Resursbankabpayments\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbankabpayments\Woocommerce\Modules\Order\Order;
use Resursbankabpayments\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbankabpayments\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbankabpayments\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbankabpayments\Woocommerce\Modules\Store\Store;
use Resursbankabpayments\Woocommerce\Settings\Filter\InvalidateCacheButton;
use Resursbankabpayments\Woocommerce\Settings\Filter\TestCallbackButton;
use Resursbankabpayments\Woocommerce\Settings\Settings;

/**
 * Module initialization class for functionality used by wp-admin.
 */
class Admin
{
    /**
     * Init various modules.
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

        if (!Enabled::isEnabled()) {
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
    }
}
