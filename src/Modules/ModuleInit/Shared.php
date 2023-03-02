<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Modules\Callback\Callback;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Order;

/**
 * Module initialization class for functionality shared between both the frontend and wp-admin.
 */
class Shared
{
    /**
     * Init various modules.
     *
     * @throws \Resursbank\Ecom\Exception\ConfigException
     */
    public static function init(): void
    {
        WordPress::initializeWooCommerce();
        Order::init();
        MessageBag::init();
        Callback::init();
    }
}
