<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Database\Options\Api\Enabled;
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

        GatewayBlocks::init();
        Gateway::initAdmin();
        Order::init();
        OrderManagement::init();
        PaymentInformation::init();
        Order::initAdmin();
    }
}
