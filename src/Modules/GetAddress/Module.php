<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress;

use Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout as Widget;

/**
 * Implementation of get address widget in checkout.
 */
class Module
{
    /**
     * Initialize module.
     */
    public static function setup(): void
    {
        Widget::register();
    }
}
