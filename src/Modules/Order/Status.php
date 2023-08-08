<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Modules\OrderManagement\Filter\BeforeOrderStatusChange;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order;

/**
 * Business logic relating to WC_Order status.
 */
class Status
{
    /**
     * Update WC_Order status based on Resurs Bank payment status.
     *
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
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
                status: self::orderStatusFromPaymentStatus(payment: $payment),
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
    public static function orderStatusFromPaymentStatus(Payment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::ACCEPTED => 'processing',
            PaymentStatus::REJECTED => self::getFailedOrCancelled(payment: $payment),
            default => 'on-hold'
        };
    }

    /**
     * Update WC_Order status based on reason for payment rejection.
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
     */
    private static function updateRejected(
        Payment $payment,
        WC_Order $order
    ): void {
        $status = self::getFailedOrCancelled(payment: $payment);

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        $order->update_status(
            $status,
            Translator::translate(phraseId: "payment-status-$status")
        );
    }

    /**
     * Gets "failed" or "cancelled" based on task completion status.
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
     */
    private static function getFailedOrCancelled(Payment $payment): string
    {
        return Repository::getTaskStatusDetails(
            paymentId: $payment->id
        )->completed ? 'failed' : 'cancelled';
    }
}
