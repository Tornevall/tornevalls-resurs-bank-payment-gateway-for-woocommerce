<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Exception;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\NotJsonEncodedException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableRefund;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Order;
use WC_Order_Refund;
use WP_Post;

/**
 * Event which executes just before order status is changed.
 */
class BeforeOrderStatusChange
{
    /**
     * Confirm action against Resurs Bank payment based on status change. If
     * changing the order status will cause an illegal action (for example,
     * captured payment cannot be captured or cancelled) we redirect back to the
     * order view with an error.
     *
     * @throws Exception
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function exec(
        string $wpStatus,
        string $wcStatus,
        WP_Post $post
    ): void {
        // Only execute for orders (HPOS compatible).
        if (
            !in_array(
                needle: $post->post_type,
                haystack: ['shop_order', 'shop_order_placehold'],
                strict: true
            )
        ) {
            return;
        }

        $postId = $post->ID;

        // Refund objects are usually new and don't need to be checked in this context. Instead, we check the parent order.
        if ($post->ID !== $post->post_parent && $post->post_parent > 0) {
            $postId = $post->post_parent;
        }

        $order = OrderManagement::getOrder(id: (int)$postId);
        $newStatus = WooCommerce::stripStatusPrefix(
            status: $_POST['order_status'] ?? ''
        );

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($order instanceof WC_Order_Refund) {
            // Refunds are handled elsewhere.
            return;
        }

        // Only continue if the order was paid through Resurs Bank.
        if (
            $order === null ||
            $newStatus === '' ||
            !Metadata::isValidResursPayment(order: $order) ||
            self::validatePaymentAction(status: $newStatus, order: $order) ||
            !self::validateEnabledPaymentAction(status: $newStatus)
        ) {
            return;
        }

        OrderManagement::logError(
            sprintf(
                Translator::translate(phraseId: 'failed-order-status-change'),
                WooCommerce::getOrderStatusName(
                    status: WooCommerce::stripStatusPrefix(status: $wcStatus)
                ),
                WooCommerce::getOrderStatusName(status: $newStatus)
            ),
            new IllegalValueException(
                message: "Failed changing order status from $wcStatus to $newStatus for $post->ID"
            ),
            $order
        );

        Route::redirectBack();
    }

    /**
     * HPOS transition handler.
     *
     * @throws IllegalValueException
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
     * @throws NotJsonEncodedException
     * @throws Exception
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function handlePostStatusTransitions(WC_Order $order, mixed $data_store): void
    {
        $order_id = $order->get_id();

        if ($order_id <= 0) {
            return;
        }

        $post = get_post($order_id);

        if (!$post instanceof WP_Post) {
            return;
        }

        // If not a shop order, ignore (HPOS compatible).
        if (
            !in_array(
                $post->post_type,
                ['shop_order', 'shop_order_placehold'],
                true
            )
        ) {
            return;
        }

        // Old = prior status according to WC, new = ongoing update. We no longer trust POST values.
        $persisted = wc_get_order($order->get_id());
        $old_status = $persisted
            ? $persisted->get_status()
            : $order->get_status('edit');
        $new_status = $order->get_status();

        if ($new_status === 'completed') {
            // Trying to complete an order, that is frozen, is not allowed, not even here.
            $payment = OrderManagement::getPayment(order: $order);

            if ($payment->isFrozen()) {
                throw new Exception(
                    Translator::translate(
                        phraseId: 'unable-to-capture-frozen-order'
                    )
                );
            }
        }

        self::exec(wpStatus: $new_status, wcStatus: $old_status, post: $post);
    }

    /**
     * Validate payment action availability based on order status.
     */
    public static function validatePaymentAction(
        string $status,
        WC_Order $order
    ): bool {
        try {
            $payment = OrderManagement::getPayment(order: $order);

            return match ($status) {
                'failed' => OrderManagement::canCancel(
                    order: $order
                ) || (!$payment->isCancelled() || $payment->status === Status::REJECTED),
                'cancelled' => OrderManagement::canCancel(
                    order: $order
                ) || ($payment->isCancelled() || $payment->status === Status::TASK_REDIRECTION_REQUIRED),
                'completed' => OrderManagement::canCapture(
                    order: $order
                ) || $payment->isCaptured(),
                'refunded' => OrderManagement::canRefund(
                    order: $order
                ) || $payment->isRefunded(),
                default => OrderManagement::canEdit(order: $order)
            };
        } catch (Throwable $error) {
            Log::error(error: $error);
            return false;
        }
    }

    /**
     * If we get positive on payment action validation, we need to make ensure the feature is enabled to proceed.
     */
    public static function validateEnabledPaymentAction(
        string $status
    ): bool {
        switch ($status) {
            case 'failed':
            case 'cancelled':
                return EnableCancel::isEnabled();

            case 'completed':
                return EnableCapture::isEnabled();

            case 'refunded':
                return EnableRefund::isEnabled();

            default:
                return false;
        }
    }
}
