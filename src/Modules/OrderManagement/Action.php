<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Refund;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic to perform payment actions (such as CAPTURE, REFUND, CANCEL).
 */
class Action
{
    /**
     * Perform payment action.
     */
    public static function exec(
        int $orderId,
        ActionType $action,
        int $refundId = 0
    ): void {
        Log::debug(
            message: "Executing payment action $action->value for order $orderId"
        );

        try {
            $order = OrderManagement::getOrder(id: $orderId);
            $payment = OrderManagement::getPayment(order: $order);

            match ($action) {
                ActionType::CAPTURE => self::capture(
                    payment: $payment,
                    order: $order
                ),
                ActionType::CANCEL => self::cancel(
                    payment: $payment,
                    order: $order
                ),
                ActionType::REFUND => self::refund(
                    payment: $payment,
                    order: $order,
                    refundId: $refundId
                ),
                ActionType::MODIFY_ORDER => self::modify(
                    payment: $payment,
                    order: $order
                ),
                default => throw new PaymentActionException(
                    message: "Unsupported  payment action $action->value"
                )
            };
        } catch (Throwable $error) {
            self::handleError(
                orderId: $orderId,
                order: $order ?? null,
                payment: $payment ?? null,
                error: $error
            );
        }

        Log::debug(
            message: "Completed payment action $action->value for order $orderId"
        );
    }

    /**
     * Handle errors from exec() method in this class. Separated to lessen
     * cognitive complexity of exec().
     */
    private static function handleError(
        Throwable $error,
        int $orderId,
        ?WC_Order $order,
        ?Payment $payment
    ): void {
        if (!isset($order)) {
            Log::error(error: $error);
            MessageBag::addError(message: sprintf(
                Translator::translate(phraseId: 'failed-resolving-order'),
                "id $orderId"
            ));
        } elseif (!isset($payment)) {
            OrderManagement::logError(
                order: $order,
                message: Translator::translate(
                    phraseId: 'failed-resolving-payment'
                ),
                error: $error
            );
        }
    }

    /**
     * Capture payment.
     */
    private static function capture(Payment $payment, WC_Order $order): void
    {
        try {
            if (!$payment->canCapture()) {
                throw new PaymentActionException(message: 'Cannot capture.');
            }

            Repository::capture(paymentId: $payment->id);
            OrderManagement::logSuccess(
                order: $order,
                message: Translator::translate(phraseId: 'capture-success')
            );
        } catch (Throwable $error) {
            OrderManagement::logError(
                order: $order,
                message: Translator::translate(
                    phraseId: 'capture-action-failed'
                ),
                error: $error
            );
        }
    }

    /**
     * Cancel payment.
     */
    private static function cancel(Payment $payment, WC_Order $order): void
    {
        try {
            if (!$payment->canCancel()) {
                throw new PaymentActionException(message: 'Cannot cancel.');
            }

            Repository::cancel(paymentId: $payment->id);
            OrderManagement::logSuccess(
                order: $order,
                message: Translator::translate(phraseId: 'cancel-success')
            );
        } catch (Throwable $error) {
            OrderManagement::logError(
                order: $order,
                message: Translator::translate(
                    phraseId: 'cancel-action-failed'
                ),
                error: $error
            );
        }
    }

    /**
     * Refund payment.
     */
    private static function refund(
        Payment $payment,
        WC_Order $order,
        int $refundId
    ): void {
        try {
            Refund::exec(payment: $payment, order: $order, refundId: $refundId);
        } catch (Throwable $error) {
            OrderManagement::logError(
                order: $order,
                message: Translator::translate(
                    phraseId: 'refund-action-failed'
                ),
                error: $error
            );
        }
    }

    /**
     * Modify payment.
     */
    private static function modify(Payment $payment, WC_Order $order): void
    {
        try {
            if ($payment->canCancel()) {
                Repository::cancel(paymentId: $payment->id);
            }

            Repository::addOrderLines(
                paymentId: $payment->id,
                orderLines: Order::getOrderLines(order: $order)
            );

            OrderManagement::logSuccess(
                order: $order,
                message: Translator::translate(phraseId: 'modify-success')
            );
        } catch (Throwable $error) {
            OrderManagement::logError(
                order: $order,
                message: Translator::translate(
                    phraseId: 'modify-action-failed'
                ),
                error: $error
            );
        }
    }
}
