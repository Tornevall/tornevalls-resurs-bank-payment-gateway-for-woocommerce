<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order;
use WP_Post;

use function strlen;

/**
 * Business logic relating to order status manipulation.
 */
class OrderStatus
{
    /**
     * Event when order status changes.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
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
     * Confirm action against Resurs Bank payment based on status change. If
     * changing the order status will cause an illegal action (for example,
     * captured payment cannot be captured or cancelled) we redirect back to the
     * order view with an error.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function validateStatusChange(
        string $wpStatus,
        string $wcStatus,
        WP_Post $post
    ): void {
        if ($post->post_type !== 'shop_order') {
            return;
        }

        $order = OrderManagement::getOrder(id: (int)$post->ID);
        $newStatus = self::stripStatusPrefix(
            status: $_POST['order_status'] ?? ''
        );

        if (
            $order !== null &&
            $newStatus !== '' &&
            self::validatePaymentAction(status: $newStatus, order: $order)
        ) {
            return;
        }

        OrderManagement::logError(
            order: $order,
            message: sprintf(
                Translator::translate(phraseId: 'failed-order-status-change'),
                self::stripStatusPrefix(status: $wcStatus),
                $newStatus
            ),
            error: new IllegalValueException(
                message: "Failed changing order status from $wcStatus to $newStatus for $post->ID"
            )
        );

        Route::redirectBack();
    }

    /**
     * Whether Resurs Bank payment is captured.
     */
    public static function isCaptured(WC_Order $order): bool
    {
        $payment = OrderManagement::getPayment(order: $order);

        if ($payment === null) {
            return false;
        }

        return $payment->isCaptured();
    }

    /**
     * Whether Resurs Bank payment is refunded.
     */
    public static function isRefunded(WC_Order $order): bool
    {
        $payment = OrderManagement::getPayment(order: $order);

        if ($payment === null) {
            return false;
        }

        return $payment->isRefunded();
    }

    /**
     * Whether Resurs Bank payment is cancelled.
     */
    public static function isCancelled(WC_Order $order): bool
    {
        $payment = OrderManagement::getPayment(order: $order);

        if ($payment === null) {
            return false;
        }

        return $payment->isCancelled();
    }

    /**
     * Validate payment action availability based on order status.
     */
    private static function validatePaymentAction(
        string $status,
        WC_Order $order
    ): bool {
        return match ($status) {
            'cancelled' => OrderManagement::canCancel(
                order: $order
            ) && !self::isCancelled(order: $order),
            'completed' => OrderManagement::canCapture(
                order: $order
            ) || self::isCaptured(order: $order),
            'refunded' => OrderManagement::canRefund(
                result: false,
                orderId: (int) $order->get_id(),
                    order: $order
            ) && !self::isRefunded(order: $order),
            default => OrderManagement::canEdit(result: false, order: $order)
        };
    }

    /**
     * Strip 'wc-' prefix from status string.
     */
    private static function stripStatusPrefix(
        string $status
    ): string {
        $result = $status;

        if (
            strlen(string: $result) > 3 &&
            str_starts_with(haystack: $status, needle: 'wc-')
        ) {
            $result = substr(string: $status, offset: 3);
        }

        return $result;
    }
}
