<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;

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
        add_action(
            'wp_enqueue_scripts',
            [self::class, 'initBackgroundAgent'],
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
     * Initialize background missions.
     *
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initBackgroundAgent(): void
    {
        if (!WooCommerce::isWcPresent() || !function_exists('WC')) {
            return;
        }

        if (!function_exists('WC')) {
            return;
        }

        $cartTotals = WooCommerce::getCartTotals();

        if ($cartTotals <= 0) {
            return;
        }

        self::registerBackgroundAgent();
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

    /**
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function registerBackgroundAgent(): void
    {
        $cartTotals = WooCommerce::getCartTotals();

        wp_register_script(
            'rb-background-agent',
            Url::getResourceUrl(
                module: 'ModuleInit',
                file: 'background-agent.js'
            ),
            ['jquery'],
            false,
            true
        );

        wp_enqueue_script('rb-background-agent');

        wp_localize_script(
            'rb-background-agent',
            'rbBackgroundAgent',
            [
                'url' => Route::getUrl(
                    route: Route::ROUTE_GET_BACKGROUND_AGENT_REQUEST
                ),
                'cartTotals' => $cartTotals,
                'can_request' => (is_cart() || is_product() || is_shop()) && $cartTotals,
            ]
        );
    }
}
