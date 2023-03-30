<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use WC_Order;

/**
 * Business logic to cancel Resurs Bank payment.
 */
class Cancel
{
    /**
     * Cancel Resurs Bank payment.
     */
    public static function exec(
        WC_Order $order
    ): void {
        if (!EnableCancel::isEnabled()) {
            return;
        }

        OrderManagement::execAction(
            order: $order,
            action: ActionType::CANCEL,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(order: $order);

                if (!$payment->canCancel()) {
                    return;
                }

                Repository::cancel(paymentId: $payment->id);
            }
        );
    }
}
