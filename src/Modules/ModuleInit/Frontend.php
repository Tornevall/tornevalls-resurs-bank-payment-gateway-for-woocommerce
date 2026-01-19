<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Module\Widget\PartPayment\Html as EcomPartPayment;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\RouteVariant;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

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
        if (WooCommerce::isUsingBlocksCheckout()) {
            GatewayBlocks::init();
        }

        Gateway::initFrontend();
        ThankYou::init();
        Failure::init();
        UniqueSellingPoint::init();

        self::renderPartPayment();
        self::renderReadMore();
    }

    /**
     * Inject assets and HTML for Part Payment widget on product pages.
     *
     * Note that, since there is no easy way to check if we are on a product
     * page or not, we simply attempt to load the assets and catch any errors
     * that may occur if we are not on a product page. We track such errors in
     * the debug log, since they may naturally occur.
     *
     * At the time of writing, there is no easy way to check if we are on a
     * product page or not, other than checking for the existence of a product
     * object, which may cause the aforementioned error.
     *
     * @return void
     */
    private static function renderPartPayment(): void
    {
        add_action(
            'wp_enqueue_scripts',
            function() {
                // Load dynamic CSS & JS.
                try {
                    // Inject dynamic Part Payment JS content in head.
                    wp_enqueue_script(
                        'rb-pp-js',
                        Route::getUrl(
                            route: RouteVariant::PartPaymentJs,
                            additionalQueryParams: [
                                'rb_pp_amount' => wc_get_product()->get_price()
                            ]
                        ),
                        [],
                        '1.0.0',
                        false // Load script in header.
                    );

                    wp_enqueue_style(
                        'rb-pp-css',
                        Route::getUrl(route: RouteVariant::PartPaymentCss),
                        [],
                        '1.0.0'
                    );
                } catch (Throwable $error) {
                    Logger::debug(message: $error);
                }

                // Load static JS.
                wp_enqueue_script(
                    'partpayment-script',
                    Url::getResourceUrl(
                        module: 'PartPayment',
                        file: 'part-payment.js'
                    ),
                    ['jquery'],
                    true
                );
            }
        );

        // Render Part Payment widget HTML.
        add_action(
            'woocommerce_single_product_summary',
            function () {
                try {
                    echo '<div id="rb-pp-widget-container">' .
                        (new EcomPartPayment(
                            amount: (float) wc_get_product()->get_price(),
                        ))->content .
                        '</div>';
                } catch (Throwable $error) {
                    Logger::debug(message: $error);
                }
            }
        );
    }

    /**
     * Render dynamic Read More assets.
     *
     * Note that we render these assets on all pages, since there is no easy
     * way to determine if the Read More link will be present on the page or
     * not.
     *
     * The links are used on product pages and the checkout page.
     *
     * @return void
     */
    private static function renderReadMore(): void
    {
        add_action(
            'wp_enqueue_scripts',
            function () {
                try {
                    // Inject dynamic Read More assets.
                    wp_enqueue_script(
                        'rb-rm-js',
                        Route::getUrl(route: RouteVariant::ReadMoreJs),
                        [],
                        '1.0.0',
                        false // Load script in header.
                    );

                    wp_enqueue_style(
                        'rb-rm-css',
                        Route::getUrl(route: RouteVariant::ReadMoreCss),
                        [],
                        '1.0.0'
                    );
                } catch (Throwable $error) {
                    Logger::error(message: $error);
                }
            }
        );
    }
}
