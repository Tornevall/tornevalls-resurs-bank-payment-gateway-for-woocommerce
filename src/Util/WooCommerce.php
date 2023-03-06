<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use function in_array;

/**
 * General methods relating to Woocommerce.
 */
class WooCommerce
{
    /**
     * Safely confirm whether WC is loaded.
     */
    public static function isAvailable(): bool
    {
        return in_array(
            needle: 'woocommerce/woocommerce.php',
            haystack: apply_filters(
                hook_name: 'active_plugins',
                value: get_option(option: 'active_plugins')
            ),
            strict: true
        );
    }
}
