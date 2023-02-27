<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Woocommerce\Modules\Order\Status;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Event executed when "Thank You" page is rendered after completing checkout.
 */
class ThankYou
{
    /**
     * Register event listener.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_thankyou',
            callback: 'Resursbank\Woocommerce\Modules\Order\Filter\ThankYou::exec',
            priority: 10,
            accepted_args: 1
        );
    }

    /**
     * 1. Use order metadata to remember that "Thank You" page has rendered.
     * 2. Sync order status in WP with payment at Resurs Bank.
     */
    public static function exec(mixed $orderId = null): void
    {
        try {
            // @todo Not sure what happens here if $order_id is null, could we affect the wrong order?
            $order = new WC_Order(order: $orderId);

            if (!$order instanceof WC_Order) {
                throw new IllegalTypeException(
                    message: "Failed to resolve order from id $orderId"
                );
            }

            if (Metadata::isThankYouTriggered(order: $order)) {
                return;
            }

            Status::update(order: $order);
            Metadata::setThankYouTriggered(order: $order);

            $order->add_order_note(
                note: Translator::translate(
                    phraseId: 'customer-landingpage-return'
                )
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }
}
