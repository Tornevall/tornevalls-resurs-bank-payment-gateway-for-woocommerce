<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Display / hide Resurs Bank payment information on order view page.
 */
class Order
{
    public static function init(): void
    {
        $paymentId = Metadata::getPaymentIdFromOrderId($_GET['id'] ?? 0);

        if ($paymentId === '') {
            return;
        }

        // Render custom stylesheet on order view, to manipulate elements
        // not manageable using hooks.
        add_action('admin_enqueue_scripts', fn () =>
            wp_enqueue_style(
                'rb-order-css',
                Url::getResourceUrl(
                    module: 'Order',
                    file: 'order.css',
                    type: ResourceType::CSS
                ),
                [],
                '1.0.0'
            )
        );

        // Add payment information box on order view page.
        add_action(
            'add_meta_boxes',
            fn () => add_meta_box(
                'resursbank_payment_info',
                'Resurs',
                static function () use ($paymentId): void {
                    try {
                        echo PaymentInformation::getWidgetHtml(paymentId: $paymentId);
                    } catch (Throwable $e) {
                        Logger::error(message: $e);
                    }
                }
            )
        );

        // Metadata fields, like the one containing the payment id from
        // Resurs Bank, will be hidden from view.
        add_filter(
            'is_protected_meta',
            fn (mixed $protected, mixed $meta_key) => str_starts_with(
                haystack: $meta_key,
                needle: RESURSBANK_MODULE_PREFIX . '_'
            ) ? true : $protected,
            10,
            2
        );
    }
}
