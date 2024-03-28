<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

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
use Resursbank\Ecom\Lib\Model\Payment\TaskStatusDetails;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Event executed when failure page is reached.
 */
class Failure
{
    /**
     * Register event listener.
     */
    public static function init(): void
    {
        add_filter(
            hook_name: 'woocommerce_order_cancelled_notice',
            callback: 'Resursbank\Woocommerce\Modules\Order\Filter\Failure::exec',
            priority: 10,
            accepted_args: 1
        );
    }

    /**
     * Add information to message on order failure page, explaining why payment
     * failed at Resurs Bank.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function exec(string $message = ''): string
    {
        $orderId = self::getOrderId();

        if ($orderId === '') {
            return $message;
        }

        try {
            $message = self::appendPaymentFailureMessage(
                message: $message,
                orderId: $orderId
            );
            Log::debug(message: sprintf('Order %s: %s.', $orderId, $message));
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $message;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function getOrderId(): string
    {
        return $_GET['order_id'] ?? '';
    }

    /**
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
    private static function appendPaymentFailureMessage(string $message, string $orderId): string
    {
        $order = OrderManagement::getOrder(id: (int)$orderId);

        if ($order === null) {
            throw new IllegalValueException(message: 'Missing order id.');
        }

        $paymentId = Metadata::getPaymentId(order: $order);
        $task = Repository::getTaskStatusDetails(paymentId: $paymentId);
        $payment = Repository::get(paymentId: $paymentId);

        return $message . ' ' . self::getFailureMessage(
            payment: $payment,
            task: $task
        );
    }

    /**
     * Get the failure message based on either TaskStatusDetails or RejectedReasons.
     */
    private static function getFailureMessage(
        Payment $payment,
        TaskStatusDetails $task
    ): string {
        if ($payment->isCreditDenied()) {
            return Translator::translate(phraseId: 'credit-denied-try-again');
        }

        return $task->completed ?
            Translator::translate(phraseId: 'payment-failed-try-again') :
            Translator::translate(phraseId: 'payment-cancelled-try-again');
    }
}
