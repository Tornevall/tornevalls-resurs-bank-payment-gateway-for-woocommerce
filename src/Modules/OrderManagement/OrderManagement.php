<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
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
     * Track resolved payments to avoid additional API calls.
     */
    private static array $payments = [];

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

        if (EnableRefund::isEnabled()) {
            // Prevent order refund options from rendering.
            add_filter(
                hook_name: 'woocommerce_admin_order_should_render_refunds',
                callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::canRefund',
                accepted_args: 3
            );

            add_action(
                hook_name: 'woocommerce_order_refunded',
                callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::refund',
                priority: 10,
                accepted_args: 2
            );
        }

        if (EnableModify::isEnabled()) {
            // Prevent order editing options from rendering.
            add_filter(
                hook_name: 'wc_order_is_editable',
                callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::canEdit',
                accepted_args: 2
            );

            add_action(
                hook_name: 'woocommerce_update_order',
                callback: 'Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement::updateOrder',
                accepted_args: 2
            );
        }

        // Break status update if unavailable based on payment status.
        add_action(
            hook_name: 'transition_post_status',
            callback: 'Resursbank\Woocommerce\Modules\OrderManagement\Filter\OrderStatus::validateStatusChange',
            accepted_args: 3
        );

        // Execute payment action AFTER status has changed in WC.
        add_action(
            hook_name: 'woocommerce_order_status_changed',
            callback: 'Resursbank\Woocommerce\Modules\OrderManagement\Filter\OrderStatus::orderStatusChanged',
            priority: 10,
            accepted_args: 3
        );
    }

    /**
     * Order can be modified as long as payment can be captured.
     */
    public static function canCapture(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);

        if ($payment === null) {
            return false;
        }

        return $payment->canCapture() || $payment->canPartiallyCapture();
    }

    /**
     * Order can be refunded as long as payment can be refunded.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function canRefund(
        bool $result,
        int $orderId,
        WC_Order $order
    ): bool {
        $payment = self::getPayment(order: $order);

        if ($payment === null) {
            return $result;
        }

        return $payment->canRefund() || $payment->canPartiallyRefund();
    }

    /**
     * Order can be cancelled as long as payment can be cancelled.
     */
    public static function canCancel(
        WC_Order $order
    ): bool {
        $payment = self::getPayment(order: $order);

        return $payment !== null && (
            $payment->canCancel() ||
            $payment->canPartiallyCancel()
        );
    }

    /**
     * Whether WC_Order can be modified based on payment.
     */
    public static function canEdit(bool $result, WC_Order $order): bool
    {
        if (!Metadata::isValidResursPayment(order: $order)) {
            return $result;
        }

        return self::canCapture(order: $order) || self::canCancel(
            order: $order
        );
    }

    /**
     * Get WC_Order from id.
     */
    public static function getOrder(int $id): ?WC_Order
    {
        $result = null;

        try {
            $result = wc_get_order(the_order: $id);

            if (!$result instanceof WC_Order) {
                throw new IllegalTypeException(
                    message: 'Returned object not of type WC_Order'
                );
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $result;
    }

    /**
     * Get Resurs Bank payment attached to WC_Order instance.
     */
    public static function getPayment(WC_Order $order): ?Payment
    {
        $result = null;
        $id = (int) $order->get_id();

        if (isset(self::$payments[$id])) {
            return self::$payments[$id];
        }

        if (Metadata::isValidResursPayment(order: $order)) {
            try {
                $result = Repository::get(
                    paymentId: Metadata::getPaymentId(order: $order)
                );

                self::$payments[$id] = $result;
            } catch (Throwable $error) {
                Log::error(error: $error);
            }
        }

        return $result;
    }

    /**
     * Add error message to order notes and message bag.
     */
    public static function logError(
        string $message,
        Throwable $error,
        ?WC_Order $order = null
    ): void {
        Log::error(error: $error);
        MessageBag::addError(message: $message);
        $order?->add_order_note(note: $message);
    }

    /**
     * Add success message to order notes and message bag.
     */
    public static function logSuccess(
        string $message,
        ?WC_Order $order = null
    ): void {
        Log::debug(message: $message);
        MessageBag::addSuccess(message: $message);
        $order?->add_order_note(note: $message);
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
     * @noinspection PhpUnusedParameterInspection
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
            ($payment->order->authorizedAmount + $payment->order->capturedAmount) === (float) $order->get_total()
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
