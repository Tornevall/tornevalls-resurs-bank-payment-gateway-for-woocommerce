<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Woocommerce\Settings\Filter\InvalidateCacheButton;

/**
 * Handles operations related to admin settings page. This class is not to be
 * confused with Resursbank\Woocommerce\Settings which extends WooCommerce
 * configuration class to render our configuration page.
 */
class Settings
{
    /**
     * Register filters (event listeners).
     */
    public static function init(): void
    {
        InvalidateCacheButton::register();
    }
}
