<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use JsonException;
use ReflectionException;
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
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Modify;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
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
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
     * @throws ReflectionException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function exec(mixed $orderId, mixed $order): void
    {
        if (
            !$order instanceof WC_Order ||
            !Metadata::isValidResursPayment(order: $order)
        ) {
            return;
        }

        if (self::canUpdate(order: $order)) {
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            $order->add_order_note(
                Translator::translate(
                    phraseId: 'order-changed-by-unknown-source'
                )
            );
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

            $handledAmount = $payment->order->authorizedAmount + $payment->order->capturedAmount;

            if ($handledAmount === (float)$order->get_total()) {
                return;
            }

            if ($payment->rejectedReason !== null) {
                /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                $order->add_order_note(
                    'Unable to modify order: ' . $payment->rejectedReason->category->value
                );
                return;
            }

            Modify::exec(payment: $payment, order: $order);
        } catch (Throwable $error) {
            self::handleError(error: $error);
        }
    }

    /**
     * Check if update should be run.
     *
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws NotJsonEncodedException
     */
    private static function canUpdate(WC_Order $order): bool
    {
        return !OrderManagement::canEdit(order: $order) &&
               !OrderManagement::canCapture(order: $order) &&
               !OrderManagement::canCancel(order: $order) &&
               !OrderManagement::canRefund(order: $order);
    }

    /**
     * Log error that occurred while updating payment at Resurs Bank. This
     * method will only track one single error instance.
     */
    private static function handleError(
        Throwable $error
    ): void {
        if (self::$modificationError) {
            return;
        }

        OrderManagement::logActionError(
            action: ActionType::MODIFY_ORDER,
            error: $error,
            reason: $error->getMessage()
        );

        self::$modificationError = true;
    }
}
