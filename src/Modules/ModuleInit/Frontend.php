<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Module\Rws\Repository as RwsRepository;
use Resursbank\Ecom\Module\Widget\PartPayment\Html as EcomPartPayment;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Util\ResourceType;
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


        // Render dynamic CSS & JS for Read More & Part Payment widget.
        Route::loadAssets([
            RouteVariant::ReadMoreJs,
            RouteVariant::ReadMoreCss,
            RouteVariant::PartPaymentCss,
            RouteVariant::PaymentMethodJs,
            [
                'route' => RouteVariant::PartPaymentJs,
                'params' => function () {
                    return ['rb_pp_amount' => wc_get_product()->get_price()];
                }
            ]
        ]);

        add_action('wp_enqueue_scripts', fn () => wp_enqueue_style(
            'rb-wc-blocks-css',
            Url::getResourceUrl(
                module: 'Gateway',
                file: 'checkout-blocks.css',
                type: ResourceType::CSS
            )
        ));

        // Load RWS payment widget script on checkout.
        self::enqueueRwsWidgetScript();

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
     * Load the RWS payment widget script on checkout pages.
     *
     * The script is loaded with specific attributes required by the RWS widget:
     * - name and id: resurs-payment-widget
     * - data-customer-type: NATURAL (default, overridden by JS)
     * - data-locale: BCP 47 language tag from Config::getLanguage()
     * - type: module
     * - crossorigin: anonymous
     *
     * We use wp_head to directly echo the script tag because WordPress's
     * enqueue system doesn't properly support all the required attributes
     * (like name, data-*, type="module") and may transform external scripts.
     */
    private static function enqueueRwsWidgetScript(): void
    {
        add_action('wp_head', static function (): void {
            if (!is_checkout()) {
                return;
            }

            $locale = Config::getLanguage()->toBcp47();
            $src = RwsRepository::getWidgetScriptUrl();

            printf(
                '<script name="resurs-payment-widget" id="resurs-payment-widget" ' .
                'data-customer-type="NATURAL" data-locale="%s" ' .
                'type="module" crossorigin="anonymous" src="%s"></script>' . "\n",
                esc_attr($locale),
                esc_url($src)
            );
        });
    }
}
