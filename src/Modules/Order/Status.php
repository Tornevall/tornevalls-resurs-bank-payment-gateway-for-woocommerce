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

        if (!self::canUpdate(order: $order, payment: $payment)) {
            return;
        }

        match ($payment->status) {
            PaymentStatus::ACCEPTED => $order->payment_complete(),
            PaymentStatus::REJECTED => self::updateRejected(
                payment: $payment,
                order: $order
            ),
            default => $order->update_status(
                new_status: 'on-hold',
                note: Translator::translate(phraseId: 'payment-status-on-hold')
            ),
        };
    }

    /**
     * Whether we may update the status of a WC_Order instance.
     *
     * 1. WC_Order may not have obtained a status which cannot be manipulated.
     * 2. Converted WC_Order status from the Resurs Bank payment most differ.
     */
    public static function canUpdate(
        WC_Order $order,
        Payment $payment
    ): bool {
        // List of statuses which cannot be overwritten.
        $statuses = ['pending', 'processing', 'on-hold'];

        return
            $order->has_status(status: $statuses) &&
            !$order->has_status(
                status: self::convertPaymentStatus(payment: $payment)
            )
        ;
    }

    /**
     * Resolve expected WC_Order status based on the status of the Payment.
     */
    public static function convertPaymentStatus(
        Payment $payment
    ): string {
        return match ($payment->status) {
            PaymentStatus::ACCEPTED => 'completed',
            PaymentStatus::REJECTED => 'failed',
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
        $status = Repository::getTaskStatusDetails(
            paymentId: $payment->id
        )->completed ? 'failed' : 'cancelled';

        $order->update_status(
            new_status: $status,
            note: Translator::translate(phraseId: "payment-status-$status")
        );
    }
}
