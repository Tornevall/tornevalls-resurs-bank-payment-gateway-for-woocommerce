<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;
use Resursbank\Woocommerce\Util\WooCommerce;

/**
 * Module initialization class for functionality used by the frontend parts of plugin.
 */
class Frontend
{
    /**
     * Init various modules.
     *
     * @throws ConfigException
     * @todo The enable check can be moved to the init.php file instead, so we do not need it in the Frontend init, the Admin init and the Shared init.
     */
    public static function init(): void
    {
        if (!Repository::isEnabled(field: Field::ENABLED)) {
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
