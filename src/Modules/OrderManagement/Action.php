<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use Resursbank\Ecom\Exception\MissingPaymentException;
use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Modify;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Refund;
use Resursbank\Woocommerce\Util\Currency;
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
            $order = self::getOrder(id: $orderId);
            $payment = self::getPayment(order: $order);

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
            OrderManagement::logError(
                message: Translator::translate(
                    phraseId: 'failed-payment-action'
                ),
                order: $order ?? null,
                error: $error
            );
        }

        Log::debug(
            message: "Completed payment action $action->value for order $orderId"
        );
    }

    /**
     * Capture payment.
     */
    private static function capture(Payment $payment, WC_Order $order): void
    {
        if (!$payment->canCapture()) {
            return;
        }

        try {
            Repository::capture(paymentId: $payment->id);
            OrderManagement::logSuccess(
                order: $order,
                message: sprintf(
                    Translator::translate(phraseId: 'capture-success'),
                    Currency::getFormattedAmount(
                        amount: (float) $order->get_total()
                    )
                )
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
        if (!$payment->canCancel()) {
            return;
        }

        try {
            Repository::cancel(paymentId: $payment->id);
            OrderManagement::logSuccess(
                order: $order,
                message: sprintf(
                    Translator::translate(phraseId: 'cancel-success'),
                    Currency::getFormattedAmount(
                        amount: (float) $order->get_total()
                    )
                )
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
        if (!$payment->canRefund()) {
            return;
        }

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
            Modify::exec(payment: $payment, order: $order);
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

    /**
     * @throws MissingPaymentException
     */
    private static function getPayment(WC_Order $order): Payment
    {
        $payment = OrderManagement::getPayment(order: $order);

        if ($payment === null) {
            throw new MissingPaymentException(
                message: (string) $order->get_id()
            );
        }

        return $payment;
    }

    /**
     * @throws IllegalValueException
     */
    private static function getOrder(int $id): WC_Order
    {
        $order = OrderManagement::getOrder(id: $id);

        if ($order === null) {
            throw new IllegalValueException(
                message: "Failed to resolve WC_Order using $id"
            );
        }

        return $order;
    }
}
