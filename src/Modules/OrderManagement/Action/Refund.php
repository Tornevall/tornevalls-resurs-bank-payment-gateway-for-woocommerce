<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;
use WC_Order_Refund;

/**
 * Business logic to perform payment actions (such as CAPTURE, REFUND, CANCEL).
 */
class Refund
{
    /**
     * @throws PaymentActionException
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public static function exec(
        Payment $payment,
        WC_Order $order,
        int $refundId
    ): void {
        if (!$payment->canRefund()) {
            throw new PaymentActionException(message: 'Cannot refund.');
        }

        $refund = self::getRefund(id: $refundId);
        $orderLines = Order::getOrderLines(order: $refund);

        if (
            !self::validate(payment: $payment, order: $order, refund: $refund)
        ) {
            return;
        }

        Repository::refund(
            paymentId: $payment->id,
            orderLines: $orderLines->count() > 0 ? $orderLines : null
        );

        if ($orderLines->count() === 0) {
            OrderManagement::logSuccess(
                order: $order,
                message: Translator::translate(phraseId: 'refund-success')
            );
        } else {
            OrderManagement::logSuccess(
                order: $order,
                message: sprintf(
                    Translator::translate(
                        phraseId: 'partial-refund-success'
                    ),
                    Currency::getFormattedAmount(
                        amount: Order::convertFloat(
                            value: $refund->get_total()
                        )
                    )
                )
            );
        }
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
                order: $order,
                message: sprintf(
                    Translator::translate(phraseId: 'refund-too-large'),
                    $requestedAmount,
                    $availableAmount
                ),
                error: $error
            );
        }

        return $result;
    }

    /**
     * @throws IllegalTypeException
     */
    private static function getRefund(int $id): WC_Order_Refund
    {
        $order = wc_get_order(the_order: $id);

        if (!$order instanceof WC_Order_Refund) {
            throw new IllegalTypeException(
                message: 'Returned object not of type WC_Order_Refund'
            );
        }

        return $order;
    }
}
