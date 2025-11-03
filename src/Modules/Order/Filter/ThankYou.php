<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Lib\Model\PaymentHistory\Entry;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Event;
use Resursbank\Ecom\Lib\Model\PaymentHistory\User;
use Resursbank\Ecom\Module\PaymentHistory\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;

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
            $order = OrderManagement::getOrder(id: $orderId);

            // Failed to find Resurs Bank order.
            if ($order === null) {
                return;
            }

            Repository::write(entry: new Entry(
                paymentId: Metadata::getPaymentId(order: $order),
                event: Event::REACHED_ORDER_SUCCESS_PAGE,
                user: User::CUSTOMER
            ));
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }
}
