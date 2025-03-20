<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\NotJsonEncodedException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Modules\OrderManagement\Filter\BeforeOrderStatusChange;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic relating to WC_Order status.
 */
class Status
{
    /**
     * Update WC_Order status based on Resurs Bank payment status.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws AttributeCombinationException
     * @throws NotJsonEncodedException
     */
    public static function update(
        WC_Order $order
    ): void {
        if (!Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        $payment = PaymentRepository::get(
            paymentId: Metadata::getPaymentId(order: $order)
        );

        if (
            $order->get_status() !== 'pending' && !BeforeOrderStatusChange::validatePaymentAction(
                status: self::orderStatusFromPaymentStatus(
                    payment: $payment,
                    order: $order
                ),
                order: $order
            )
        ) {
            return;
        }

        // We don't handle INSPECTION or TASK_REDIRECTION_REQUIRED as the former can't appear in the e-commerce flow
        // and the latter should not be possible after we've received a callback.
        match ($payment->status) {
            PaymentStatus::ACCEPTED => $order->payment_complete(),
            PaymentStatus::REJECTED => self::updateRejected(
                payment: $payment,
                order: $order
            ),
            default => $order->update_status(
                new_status: 'on-hold',
                note: Translator::translate(phraseId: 'payment-status-on-hold')
            )
        };
    }

    /**
     * Translates a Resurs payment status to a WooCommerce order status string.
     *
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function orderStatusFromPaymentStatus(Payment $payment, WC_Order $order): string
    {
        return match ($payment->status) {
            PaymentStatus::ACCEPTED => 'processing',
            PaymentStatus::REJECTED => self::getFailedOrCancelled(
                payment: $payment,
                order: $order
            ),
            default => 'on-hold'
        };
    }

    /**
     * Update WC_Order status based on reason for payment rejection.
     *
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    private static function updateRejected(
        Payment $payment,
        WC_Order $order
    ): void {
        $status = self::getFailedOrCancelled(payment: $payment, order: $order);
        $orderStatus = $order->get_status();

        // If Resurs status of the payment is set to cancellation and
        // woocommerce status is not yet cancelled, then we're allowed to
        // change the status of the order.
        if ($status !== 'cancelled' && $status !== 'failed') {
            return;
        }

        if (
            $orderStatus === 'cancelled' &&
            $status === 'failed' ||
            $orderStatus === $status
        ) {
            return;
        }

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection Keep WP compatibility. */
        $order->update_status(
            $status,
            Translator::translate(phraseId: "payment-status-$status")
        );
    }

    /**
     * Gets "failed" or "cancelled" based on task completion status.
     *
     * @throws ConfigException
     */
    private static function getFailedOrCancelled(Payment $payment, WC_Order $order): string
    {
        try {
            $taskStatusDetails = Repository::getTaskStatusDetails(
                paymentId: $payment->id
            );
            $defaultStatus = $taskStatusDetails->completed
                ? 'failed'
                : 'cancelled';

            // By default, we always return the expected status from the task status details.
            // However, we allow filtering of the status to be returned if modifications are required by someone else.
            // In this case, we are forwarding the default status, the task status details, the payment, and the order.
            // Note: For $taskStatusDetails, the point of interest here is the "complete" value.
            $returnStatus = apply_filters(
                'resurs_payment_task_status',
                $defaultStatus,
                $taskStatusDetails,
                $payment,
                $order
            ) ?? $defaultStatus;
        } catch (Throwable $e) {
            $returnStatus = $defaultStatus ?? 'failed';
            Config::getLogger()->error(message: $e);
        }

        return $returnStatus;
    }
}
