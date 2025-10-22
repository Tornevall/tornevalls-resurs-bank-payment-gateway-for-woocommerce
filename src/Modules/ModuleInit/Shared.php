<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\Callback\Callback;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;
use WC_Order;

/**
 * Module initialization class for functionality shared between both the frontend and wp-admin.
 */
class Shared
{
    /**
     * Init various modules.
     *
     * @throws ConfigException
     * @todo The enable check can be moved to the init.php file instead, so we do not need it in the Frontend init, the Admin init and the Shared init.
     */
    public static function init(): void
    {
        // Preload cached currency data.
        Currency::getWooCommerceCurrencySymbol();
        Currency::getWooCommerceCurrencyFormat();

        // Things that should be available even without the plugin API being enabled.
        Route::exec();
        MessageBag::init();

        if (!Repository::isEnabled(field: Field::ENABLED)) {
            return;
        }

        // Assets must be enqueued, not called directly.
        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\AssetLoader::init'
        );

        Gateway::init();
        Callback::init();
        GetAddress::init();
    }

    /**
     * Registers filters related to payment status handling.
     * Not in use - for the moment.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     * @todo Currently not in use. Should be used to handle payment status changes on future decision.
     */
    private static function registerStatusFilters(): void
    {
        // This filter handles a maximum of 4 arguments, but since we only use this internally, we onlu use two of
        // them. Creating own filters, however, may require all 4 arguments to make sure payment and order data is
        // correct, before triggering further actions. This filter should be used with caution.
        add_filter(
            'resurs_payment_task_status',
            static fn (string $status, $taskStatusDetails, Payment $payment, WC_Order $order): string => 'cancelled',
            10,
            4
        );
    }
}
