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
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic to modify Resurs Bank payment.
 */
class Modify
{
    /**
     * Modify content of Resurs Bank payment.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws PaymentActionException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     */
    public static function exec(
        Payment $payment,
        WC_Order $order
    ): void {
        if (!self::validate(payment: $payment, order: $order)) {
            return;
        }

        OrderManagement::execAction(
            order: $order,
            action: ActionType::MODIFY_ORDER,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(order: $order);

                if ($payment->canCancel()) {
                    Repository::cancel(paymentId: $payment->id);
                }

                Repository::addOrderLines(
                    paymentId: $payment->id,
                    orderLines: Order::getOrderLines(order: $order)
                );
            }
        );
    }

    /**
     * Whether requested modify amount is possible against Resurs Bank payment.
     *
     * @throws PaymentActionException
     * @throws Throwable
     */
    private static function validate(
        Payment $payment,
        WC_Order $order
    ): bool {
        $result = true;

        $availableAmount = $payment->application->approvedCreditLimit;

        try {
            $requestedAmount = $order->get_total();

            if (!is_numeric(value: $requestedAmount)) {
                throw new PaymentActionException(
                    message: 'Order amount is not numeric.'
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
                    Translator::translate(phraseId: 'modify-too-large'),
                    Currency::getFormattedAmount(amount: $requestedAmount),
                    Currency::getFormattedAmount(amount: $availableAmount)
                ),
                error: $error
            );
        }

        return $result;
    }
}
