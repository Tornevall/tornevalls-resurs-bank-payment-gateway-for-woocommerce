<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Database\Options\Advanced\CancelledStatusHandling;
use Resursbank\Woocommerce\Database\Options\Advanced\FailedStatusHandling;
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
        self::registerStatusFilters();
    }

    /**
     * Registers filters related to payment status handling.
     */
    private static function registerStatusFilters(): void
    {
        // This filter handles a maximum of 4 arguments, but since we only use this internally, we onlu use two of
        // them. Creating own filters, however may require all 4 arguments to make sure payment and order data is
        // correct, before trigging further actions. This filter should be used with caution.
        add_filter(
            'resurs_payment_task_status',
            static function (string $status, $taskStatusDetails): string {
                $failedHandling = FailedStatusHandling::getData();
                $cancelledHandling = CancelledStatusHandling::getData();

                // Check the task status details and determine the status.
                if ($taskStatusDetails->completed) {
                    // If completed, use the configured failed handling.
                    return $failedHandling;
                }

                // If not completed, use the configured cancelled handling.
                return $cancelledHandling;
            },
            10,
            4
        );
    }
}
