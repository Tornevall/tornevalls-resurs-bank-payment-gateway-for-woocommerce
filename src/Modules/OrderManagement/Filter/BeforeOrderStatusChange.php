<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use WP_Post;

use function strlen;

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
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function exec(
        string $wpStatus,
        string $wcStatus,
        WP_Post $post
    ): void {
        // Only execute for orders.
        if ($post->post_type !== 'shop_order') {
            return;
        }

        $order = OrderManagement::getOrder(id: (int)$post->ID);
        $newStatus = self::stripStatusPrefix(
            status: $_POST['order_status'] ?? ''
        );

        // Only continue if order was paid through Resurs Bank.
        if (
            $order === null ||
            $newStatus === '' ||
            !Metadata::isValidResursPayment(order: $order) ||
            OrderManagement::validatePaymentAction(
                status: $newStatus,
                order: $order
            )
        ) {
            return;
        }

        OrderManagement::logError(
            message: sprintf(
                Translator::translate(phraseId: 'failed-order-status-change'),
                self::getOrderStatusName(
                    status: self::stripStatusPrefix(status: $wcStatus)
                ),
                self::getOrderStatusName(status: $newStatus)
            ),
            error: new IllegalValueException(
                message: "Failed changing order status from $wcStatus to $newStatus for $post->ID"
            ),
            order: $order
        );

        Route::redirectBack();
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

    /**
     * Type-safe wrapper for wc_get_order_status_name.
     */
    private static function getOrderStatusName(string $status): string
    {
        $name = wc_get_order_status_name(status: $status);

        if (!is_string(value: $name)) {
            return $status;
        }

        return $name;
    }
}
