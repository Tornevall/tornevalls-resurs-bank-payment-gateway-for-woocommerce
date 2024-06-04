<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Lib\Model\Payment;
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
            callback: static function () use ($order): void {
                /**
                 * If the order has primarily been handled by another action point at an earlier stage,
                 * it will conflict with the shutdown filter. This filter does not have enough time
                 * to make a new payment request to Resurs, leading to an incorrect response being used
                 * as the basis for another cancel request (if that is what the order intends).
                 * Since the order has not been updated in that scenario, the response is typically already
                 * available in the action that last processed the order. Therefore, this response should be used
                 * primarily before making a new get request to the Resurs API, if it exists.
                 * This should not be confused with caching, though initially, we attempted to manage it with globals.
                 */
                if (
                    isset(OrderManagement::$onShutdownPreparedResursPayment) &&
                    OrderManagement::$onShutdownPreparedResursPayment instanceof Payment
                ) {
                    $payment = OrderManagement::$onShutdownPreparedResursPayment;
                } else {
                    $payment = OrderManagement::getPayment(order: $order);
                }

                // If Resurs payment status is still in redirection, the order can not be cancelled, but for
                // cancels we must allow wooCommerce to cancel orders (especially pending orders), since
                // they tend to disappear if we throw exceptions.
                if (
                    !$payment->canCancel() ||
                    $payment->status === Status::TASK_REDIRECTION_REQUIRED
                ) {
                    return;
                }

                OrderManagement::$onShutdownPreparedResursPayment = Repository::cancel(
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
