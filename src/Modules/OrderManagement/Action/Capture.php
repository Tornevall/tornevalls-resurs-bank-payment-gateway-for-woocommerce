<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic to perform payment action CAPTURE.
 */
class Capture
{
    /**
     * Execute refund payment action.
     */
    public static function exec(
        WC_Order $order
    ): void {
        if (!EnableCapture::isEnabled()) {
            return;
        }

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $payment = OrderManagement::getPayment(order: $order);

            if (!$payment->canCapture()) {
                return;
            }

            Repository::capture(paymentId: $payment->id);

            OrderManagement::logSuccessPaymentAction(
                action: ActionType::CAPTURE,
                order: $order
            );
        } catch (Throwable $error) {
            OrderManagement::logError(
                message: Translator::translate(
                    phraseId: 'capture-action-failed'
                ),
                error: $error,
                order: $order
            );
        }
    }
}
