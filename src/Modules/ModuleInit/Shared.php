<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Modules\Callback\Callback;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;

/**
 * Module initialization class for functionality shared between both the frontend and wp-admin.
 */
class Shared
{
    /**
     * Init various modules.
     */
    public static function init(): void
    {
        // Preload cached currency data.
        Currency::getWooCommerceCurrencySymbol();
        Currency::getWooCommerceCurrencyFormat();

        // Things that should be available even without the plugin API being enabled.
        Route::exec();
        MessageBag::init();

        if (!Enabled::isEnabled()) {
            return;
        }

        // Assets must be enqueued, not called directly.
        add_action(
            hook_name: 'wp_enqueue_scripts',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\AssetLoader::init'
        );

        Gateway::init();
        Callback::init();
        GetAddress::init();
    }
}
