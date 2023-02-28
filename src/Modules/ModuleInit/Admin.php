<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Modules\Ordermanagement\Ordermanagement;
use Resursbank\Woocommerce\Settings\Filter\InvalidateCacheButton;
use Resursbank\Woocommerce\Settings\Filter\PartPaymentPeriod;
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
        Ordermanagement::init();
        InvalidateCacheButton::init();
        PartPaymentPeriod::init();
        Settings::init();
    }
}
