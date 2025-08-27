<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;
use WC_Order_Refund;

/**
 * Business logic to refund Resurs Bank payment.
 */
class Refund extends Action
{
    /**
     * Refund Resurs Bank payment.
     */
    public static function exec(
        WC_Order $order,
        WC_Order_Refund $refund
    ): void {
        OrderManagement::execAction(
            action: ActionType::REFUND,
            order: $order,
            static function () use ($order, $refund): void {
                $payment = OrderManagement::getPayment(order: $order);

                if (!$payment->canRefund()) {
                    return;
                }

                $orderLines = Order::getOrderLines(order: $refund);

                if (
                    !self::validate(
                        payment: $payment,
                        order: $order,
                        refund: $refund
                    )
                ) {
                    return;
                }

                $transactionId = self::generateTransactionId();

                $response = Repository::refund(
                    paymentId: $payment->id,
                    orderLines: $orderLines->count() > 0 ? $orderLines : null,
                    transactionId: $transactionId
                );

                $action = $response->order?->actionLog->getByTransactionId(
                    id: $transactionId
                );

                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::REFUND,
                    order: $order,
                    amount: $action?->orderLines->getTotal()
                );
            }
        );
    }

    /**
     * Whether requested refund amount is possible against Resurs Bank payment.
     *
     * @throws PaymentActionException
     * @throws Throwable
     */
    private static function validate(
        Payment $payment,
        WC_Order $order,
        WC_Order_Refund $refund
    ): bool {
        $result = true;

        $availableAmount = $payment->order->capturedAmount - $payment->order->refundedAmount;

        try {
            $requestedAmount = $refund->get_amount();

            if (!is_numeric(value: $requestedAmount)) {
                throw new PaymentActionException(
                    message: 'Refund amount is not numeric.'
                );
            }

            if ((float) $requestedAmount > $availableAmount) {
                throw new PaymentActionException(
                    message: "Requested amount $requestedAmount exceeds $availableAmount on $payment->id"
                );
            }
        } catch (Throwable $error) {
            $result = false;

            if (!isset($requestedAmount)) {
                throw $error;
            }

            OrderManagement::logError(
                message: sprintf(
                    Translator::translate(phraseId: 'refund-too-large'),
                    $requestedAmount,
                    $availableAmount
                ),
                error: $error,
                order: $order
            );
        }

        return $result;
    }
}
