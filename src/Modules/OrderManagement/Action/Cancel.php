<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use WC_Order;

/**
 * Business logic to cancel Resurs Bank payment.
 */
class Cancel extends Action
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
            action: ActionType::CANCEL,
            order: $order,
            static function () use ($order): void {
                $payment = OrderManagement::getPayment(
                    order: $order
                );

                // If Resurs payment status is still in redirection, the order can not be cancelled, but for
                // cancels we must allow wooCommerce to cancel orders (especially pending orders), since
                // they tend to disappear if we throw exceptions.
                if (
                    !$payment->canCancel() ||
                    $payment->status === Status::TASK_REDIRECTION_REQUIRED
                ) {
                    return;
                }

                OrderManagement::$hasActiveCancel = true;
                Repository::cancel(
                    paymentId: $payment->id
                );
                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::CANCEL,
                    order: $order
                );
            }
        );
    }
}
