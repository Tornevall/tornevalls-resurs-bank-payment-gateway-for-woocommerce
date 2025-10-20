<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\UserSettings\Field;
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

/**
 * Module initialization class for functionality used by wp-admin.
 */
class Admin
{
    /**
     * Init various modules.
     *
     * @throws ConfigException
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
    }
}
