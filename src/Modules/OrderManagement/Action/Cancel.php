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
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
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

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $payment = OrderManagement::getPayment(order: $order);

            if (!$payment->canCancel()) {
                return;
            }

            Repository::cancel(paymentId: $payment->id);

            OrderManagement::logSuccessPaymentAction(
                action: ActionType::CANCEL,
                order: $order
            );
        } catch (Throwable $error) {
            OrderManagement::logError(
                message: Translator::translate(
                    phraseId: 'cancel-action-failed'
                ),
                error: $error,
                order: $order
            );
        }
    }
}
