<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\MissingPaymentException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableModify;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableRefund;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Order;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic relating to order management functionality.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class OrderManagement
{
    /**
     * During a request the event to update an order may execute several times,
     * and if we cannot update the payment at Resurs Bank to reflect changes
     * applied on the order in WC, we will naturally stack errors. We use this
     * flag to prevent this.
     */
    private static bool $triedModify = false;

    /**
     * The actual method that sets up actions for order status change hooks.
     */
    public static function init(): void
    {
        if (!Enabled::isEnabled()) {
            return;
        }

        add_action(
            hook_name: 'woocommerce_order_status_changed',
            callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::orderStatusChanged',
            priority: 10,
            accepted_args: 3
        );

        if (EnableRefund::isEnabled()) {
            add_action(
                hook_name: 'woocommerce_order_refunded',
                callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::refund',
                priority: 10,
                accepted_args: 2
            );
        }

        if (!EnableModify::isEnabled()) {
            return;
        }

        add_action(
            hook_name: 'woocommerce_update_order',
            callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::updateOrder',
            accepted_args: 2
        );
    }

    /**
     * @throws IllegalTypeException
     */
    public static function getOrder(int $id): WC_Order
    {
        $order = wc_get_order(the_order: $id);

        if (!$order instanceof WC_Order) {
            throw new IllegalTypeException(
                message: 'Returned object not of type WC_Order'
            );
        }

        return $order;
    }

    /**
     * Add error message to order notes and message bag.
     */
    public static function logError(
        WC_Order $order,
        string $message,
        Throwable $error
    ): void {
        Log::error(error: $error);
        MessageBag::addError(message: $message);
        $order->add_order_note(note: $message);
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws MissingPaymentException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function getPayment(WC_Order $order): Payment
    {
        $payment = Repository::get(
            paymentId: Metadata::getPaymentId(order: $order)
        );

        if (!Metadata::isValidResursPayment(order: $order)) {
            throw new MissingPaymentException(
                message: 'Order not paid using Resurs Bank.'
            );
        }

        return $payment;
    }

    /**
     * Event when order status changes.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function orderStatusChanged(
        int $orderId,
        string $old,
        string $new
    ): void {
        if ($new === 'completed' && EnableCapture::isEnabled()) {
            Action::exec(orderId: $orderId, action: ActionType::CAPTURE);
        }

        if ($new !== 'cancelled' || !EnableCancel::isEnabled()) {
            return;
        }

        Action::exec(orderId: $orderId, action: ActionType::CANCEL);
    }

    /**
     * Event executed when order refund is initiated.
     */
    public static function refund(int $orderId, int $refundId): void
    {
        Action::exec(
            orderId: $orderId,
            action: ActionType::REFUND,
            refundId: $refundId
        );
    }

    /**
     * Event executed whenever order is updated.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function updateOrder(mixed $orderId, mixed $order): void
    {
        if (
            self::$triedModify ||
            !$order instanceof WC_Order ||
            !Metadata::isValidResursPayment(order: $order)
        ) {
            return;
        }

        try {
            $payment = Repository::get(
                paymentId: Order::getPaymentId(order: $order)
            );
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: Translator::translate(phraseId: 'modify-action-failed')
            );
        }

        if (
            !isset($payment) ||
            $payment->order->authorizedAmount === (float) $order->get_total()
        ) {
            return;
        }

        self::$triedModify = true;

        Action::exec(
            orderId: (int) $order->get_id(),
            action: ActionType::MODIFY_ORDER
        );
    }
}
