<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;

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

        add_action('wp_enqueue_scripts', [self::class, 'enableConsoleLogs'], 1);

        GatewayBlocks::init();
        Gateway::initFrontend();
        ThankYou::init();
        Failure::init();
        PartPayment::initFrontend();
        GetAddress::init();
        UniqueSellingPoint::init();
    }

    /**
     * Enable logging to console for widget code, but based on the configured logLevel.
     */
    public static function enableConsoleLogs(): void
    {
        echo "<script>
        function resursConsoleLog(message, logLevel = 'INFO') {
            if (typeof rbFrontendData !== 'undefined') {
                const currentLogLevel = rbFrontendData.logLevel;
                if (currentLogLevel === 'DEBUG' || (currentLogLevel === 'INFO' && logLevel === 'INFO')) {
                    console.log(message);
                }
            }
        }
    </script>";
    }
}
