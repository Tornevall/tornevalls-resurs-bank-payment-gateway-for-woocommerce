<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Woocommerce\Modules\Order\Status;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

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
    public static function init(): void
    {
        add_action(
            'woocommerce_thankyou',
            'Resursbank\Woocommerce\Modules\Order\Filter\ThankYou::exec',
            10,
            1
        );
    }

    /**
     * 1. Use order metadata to remember that "Thank You" page has rendered.
     * 2. Sync order status in WP with payment at Resurs Bank.
     */
    public static function exec(mixed $orderId = null): void
    {
        try {
            if ($orderId === null) {
                throw new EmptyValueException(message: 'Order ID is null');
            }

            $order = OrderManagement::getOrder(id: $orderId);

            if ($order === null) {
                throw new IllegalValueException(
                    message: 'Failed to obtain order data.'
                );
            }

            if (
                !Metadata::isValidResursPayment(order: $order) ||
                Metadata::isThankYouTriggered(order: $order)
            ) {
                return;
            }

            Status::update(order: $order);
            Metadata::setThankYouTriggered(order: $order);

            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            $order->add_order_note(
                esc_html(Translator::translate(
                    phraseId: 'customer-landingpage-return'
                ))
            );

            // Clear the last order tracking to prevent issues with subsequent checkouts
            WcSession::unset(key: 'resursbank_last_payment_order');
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }
}
