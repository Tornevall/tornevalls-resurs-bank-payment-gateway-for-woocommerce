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
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic to modify Resurs Bank payment.
 */
class Modify extends Action
{
    /**
     * Used to ensure that we don't make multiple attempts to modify the payment.
     */
    private static bool $hasAlreadyLogged = false;

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
        if (
            $payment->status === Status::TASK_REDIRECTION_REQUIRED ||
            !self::validate(payment: $payment, order: $order)
        ) {
            return;
        }

        OrderManagement::execAction(
            action: ActionType::MODIFY_ORDER,
            order: $order,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(order: $order);

                // If Resurs payment status is still in redirection, the order can not be cancelled, but for
                // cancels we must allow wooCommerce to cancel orders (especially those in pending), since
                // they tend to disappear if we throw exceptions.
                if (
                    !$payment->canCancel() ||
                    $payment->status === Status::TASK_REDIRECTION_REQUIRED
                ) {
                    return;
                }

                if ($payment->canCancel()) {
                    Repository::cancel(paymentId: $payment->id);
                }

                $orderLines = Order::getOrderLines(order: $order);

                if (count($orderLines) > 0 && $orderLines->getTotal() > 0) {
                    Repository::addOrderLines(
                        paymentId: $payment->id,
                        orderLines: $orderLines
                    );
                }

                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::MODIFY_ORDER,
                    order: $order,
                    amount: (float) $order->get_total()
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

            return true;
        } catch (Throwable $error) {
            if (!isset($requestedAmount)) {
                throw $error;
            }

            self::handleValidationError(
                error: $error,
                requestedAmount: (float)$requestedAmount,
                availableAmount: (float)$availableAmount,
                order: $order
            );
            return false;
        }
    }

    /**
     * Handle logging of validation errors.
     */
    private static function handleValidationError(
        Throwable $error,
        float $requestedAmount,
        float $availableAmount,
        WC_Order $order
    ): void {
        if (self::$hasAlreadyLogged) {
            return;
        }

        OrderManagement::logError(
            message: sprintf(
                Translator::translate(phraseId: 'modify-too-large'),
                Currency::getFormattedAmount(
                    amount: (float)$requestedAmount
                ),
                Currency::getFormattedAmount(
                    amount: (float)$availableAmount
                )
            ),
            error: $error,
            order: $order
        );

        self::$hasAlreadyLogged = true;
    }
}
