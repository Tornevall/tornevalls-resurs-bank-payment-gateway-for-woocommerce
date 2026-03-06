<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\ModuleInit;

use Resursbankabpayments\Woocommerce\Database\Options\Api\Enabled;
use Resursbankabpayments\Woocommerce\Modules\Gateway\Gateway;
use Resursbankabpayments\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbankabpayments\Woocommerce\Modules\Order\Filter\Failure;
use Resursbankabpayments\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbankabpayments\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbankabpayments\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;
use Resursbankabpayments\Woocommerce\Util\WooCommerce;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module initialization class for functionality used by the frontend parts of plugin.
 */
class Frontend
{
    /**
     * Init various modules.
     */
    public static function init(): void
    {
        if (!Enabled::isEnabled()) {
            return;
        }

        add_action(
            'wp_enqueue_scripts',
            [self::class, 'enableConsoleLogs'],
            1
        );

        if (WooCommerce::isUsingBlocksCheckout()) {
            GatewayBlocks::init();
        }

        Gateway::initFrontend();
        ThankYou::init();
        Failure::init();
        PartPayment::initFrontend();
        UniqueSellingPoint::init();
    }

    /**
     * Enable logging to console for widget code, but based on the configured logLevel.
     */
    public static function enableConsoleLogs(): void
    {
        echo "<script>
        function resursbankabpaygwConsoleLog(message, logLevel = 'INFO') {
            if (typeof resursbankabpaygwFrontendData !== 'undefined') {
                const currentLogLevel = resursbankabpaygwFrontendData.logLevel;
                if (currentLogLevel === 'DEBUG' || (currentLogLevel === 'INFO' && logLevel === 'INFO')) {
                    console.log(message);
                }
            }
        }
    </script>";
    }
}
