<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

/**
 * Sets up actions for order status change hooks. Called from PluginHooks::getActions.
 */
class Module
{
    public static function setupActions(): void
    {
        add_action(
            hook_name: 'woocommerce_order_status_completed',
            callback: 'Resursbank\Woocommerce\Modules\Ordermanagement\Completed::capture',
            priority: 10,
            accepted_args: 1
        );
    }
}
