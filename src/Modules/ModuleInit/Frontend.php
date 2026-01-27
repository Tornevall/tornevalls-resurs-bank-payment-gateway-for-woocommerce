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
}
