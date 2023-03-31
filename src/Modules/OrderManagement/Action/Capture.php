<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Lib\Utilities\Strings;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use WC_Order;

/**
 * Business logic to capture Resurs Bank payment.
 */
class Capture
{
    /**
     * Capture Resurs Bank payment.
     */
    public static function exec(
        WC_Order $order
    ): void {
        if (!EnableCapture::isEnabled()) {
            return;
        }

        OrderManagement::execAction(
            order: $order,
            action: ActionType::CAPTURE,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(order: $order);

                if (!$payment->canCapture()) {
                    return;
                }

                $uuid = Strings::getUuid();

                $response = Repository::capture(
                    paymentId: $payment->id,
                    transactionId: $uuid
                );

                $action = $response->order?->actionLog->getByTransactionId(
                    id: $uuid
                );

                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::CAPTURE,
                    order: $order,
                    amount: $action?->orderLines->getTotal()
                );
            }
        );
    }
}
