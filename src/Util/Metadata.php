<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Throwable;
use WC_Order;

use function is_array;

/**
 * Order metadata handler.
 *
 * @psalm-suppress MissingDependency
 */
class Metadata
{
    public const KEY_PAYMENT_ID = RESURSBANK_MODULE_PREFIX . '_payment_id';
    public const KEY_LEGACY_ORDER_REFERENCE = 'paymentId';
    public const KEY_THANK_YOU = RESURSBANK_MODULE_PREFIX . '_thankyou_trigger';
    public const KEY_PAYMENT_METHOD = RESURSBANK_MODULE_PREFIX . '_payment_method';

    /**
     * Store UUID of Resurs Bank payment on order.
     */
    public static function setPaymentId(
        WC_Order $order,
        string $id
    ): void {
        self::setOrderMeta(
            order: $order,
            key: self::KEY_PAYMENT_ID,
            value: $id
        );
    }

    /**
     * Get UUID of Resurs Bank payment attached to order.
     */
    public static function getPaymentId(WC_Order $order): string
    {
        $paymentId = self::getOrderMeta(
            order: $order,
            key: self::KEY_PAYMENT_ID
        );

        if ($paymentId === '') {
            $paymentId = self::findPaymentIdForLegacyOrder(order: $order);
            self::setOrderMeta(
                order: $order,
                key: self::KEY_PAYMENT_ID,
                value: $paymentId
            );
        }

        return $paymentId;
    }

    /**
     * Set metadata to an order.
     * Metadata is stored uniquely (meaning the returned data from getOrderMeta can be returned as $single=true).
     */
    public static function setOrderMeta(
        WC_Order $order,
        string $key,
        string $value
    ): bool {
        $exists = metadata_exists(
            meta_type: 'post',
            object_id: $order->get_id(),
            meta_key: $key
        );

        if ($exists) {
            return (bool)update_post_meta(
                post_id: $order->get_id(),
                meta_key: $key,
                meta_value: $value
            );
        }

        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: $key,
            meta_value: $value,
            unique: true
        );
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     */
    public static function getOrderMeta(
        WC_Order $order,
        string $key
    ): string {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: $key,
            single: true
        );
    }

    /**
     * Check if current order is a valid Resurs Payment.
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        try {
            return self::getPaymentId(order: $order) !== '';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return false;
        }
    }

    /**
     * Retrieve order associated with payment id.
     */
    public static function getOrderByPaymentId(string $paymentId): ?WC_Order
    {
        $result = null;

        $orders = wc_get_orders(args: [
            'meta_key' => self::KEY_PAYMENT_ID,
            'meta_value' => $paymentId,
            'meta_compare' => '=',
        ]);

        if (
            is_array(value: $orders) &&
            count($orders) === 1 &&
            $orders[0] instanceof WC_Order
        ) {
            $result = $orders[0];
        }

        return $result;
    }

    /**
     * Add metadata to WC_Order indicating the "Thank You" page was reached.
     */
    public static function setThankYouTriggered(
        WC_Order $order
    ): void {
        self::setOrderMeta(order: $order, key: self::KEY_THANK_YOU, value: '1');
    }

    /**
     * Whether the "Thank You" page has been rendered for this order.
     */
    public static function isThankYouTriggered(
        WC_Order $order
    ): bool {
        return self::getOrderMeta(
            order: $order,
            key: self::KEY_THANK_YOU
        ) === '1';
    }

    /**
     * Attempts to use stored order reference on legacy orders to find
     */
    private static function findPaymentIdForLegacyOrder(WC_Order $order): string
    {
        try {
            $orderReference = self::getOrderMeta(
                order: $order,
                key: self::KEY_LEGACY_ORDER_REFERENCE
            );
            $result = Repository::search(
                storeId: StoreId::getData(),
                orderReference: $orderReference
            );

            if ($result->count() > 0) {
                /** @var Payment $payment */
                $payment = $result->getData()[0];
                return $payment->id;
            }

            throw new EmptyValueException(
                message: 'No results found when searching for legacy order.'
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
            throw $error;
        }
    }
}
