<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Modify;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Event triggered when order is updated.
 */
class UpdateOrder
{
    /**
     * During a request the event to update an order may execute several times,
     * and if we cannot update the payment at Resurs Bank to reflect changes
     * applied on the order in WC, we will naturally stack errors. We use this
     * flag to prevent this.
     */
    private static bool $modificationError = false;

    /**
     * @param mixed $orderId
     * @param mixed $order
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function exec(mixed $orderId, mixed $order): void
    {
        if (
            !$order instanceof WC_Order ||
            !Metadata::isValidResursPayment(order: $order)
        ) {
            return;
        }

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            /* WC will update the order several times within the same request
               cycle. Stashing the payment fetched from the API can thus can
               a false positive when comparing captured / authorized totals.
               We are therefore required to fetch a fresh payment each time. */
            $payment = Repository::get(
                paymentId: Metadata::getPaymentId(order: $order)
            );

            // Resurs will not be able to modify FROZEN orders, so they must be ACCEPTED.
            if ($payment->status !== Status::ACCEPTED) {
                throw new HttpException(
                    message: 'Invalid payment status: ' . $payment->status->value
                );
            }

            $handledAmount = $payment->order->authorizedAmount + $payment->order->capturedAmount;

            if ($handledAmount === (float)$order->get_total()) {
                return;
            }

            if ($payment->rejectedReason === null) {
                Modify::exec(payment: $payment, order: $order);
            } else {
                $order->add_order_note(
                    'Unabled to modify order: ' . $payment->rejectedReason->category->value
                );
            }
        } catch (Throwable $error) {
            self::handleError(error: $error, order: $order);
        }
    }

    /**
     * Log error that occurred while updating payment at Resurs Bank. This
     * method will only track one single error instance.
     */
    private static function handleError(
        Throwable $error,
        WC_Order $order
    ): void {
        if (self::$modificationError) {
            return;
        }

        OrderManagement::logActionError(
            action: ActionType::MODIFY_ORDER,
            order: $order,
            error: $error,
            reason: $error->getMessage()
        );

        self::$modificationError = true;
    }
}
