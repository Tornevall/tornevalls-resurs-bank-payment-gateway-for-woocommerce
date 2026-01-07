<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Ecom\Lib\Log\Logger;
use Throwable;
use WC_Abstract_Order;
use WC_Order;

use function get_class;

/**
 * Order metadata handler.
 *
 * @psalm-suppress MissingDependency
 */
class Metadata
{
    public const KEY_PAYMENT_ID = RESURSBANK_MODULE_PREFIX . '_payment_id';
    public const KEY_LEGACY_ORDER_REFERENCE = 'paymentId';

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
     * CRUD Compatible.
     *
     * @todo This can be simplieid. First of all, we will rarely fetch legacy payments, so storing the resolved payment id is an expensive because of the increased maintenance. Empty payment id should simply be returned as an empty string without throwing. We should actually isntead check where this is used inside Ecom, and ensure that _those_ places do not accept empty strings or function with empty strings. Commented those sections for now to see effectrs.
     */
    public static function getPaymentId(WC_Abstract_Order $order): string
    {
        $paymentId = self::getOrderMeta(
            order: $order,
            key: self::KEY_PAYMENT_ID
        );

        if ($paymentId === '' && self::isLegacyOrder(order: $order)) {
            $paymentId = self::findPaymentIdForLegacyOrder(order: $order);
        }

        return $paymentId;
    }

    /**
     * Set metadata to an order.
     * Metadata is stored uniquely (meaning the returned data from getOrderMeta can be returned as $single=true).
     * CRUD Compatible.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function setOrderMeta(
        WC_Abstract_Order $order,
        string $key,
        string $value
    ): bool {
        $order->meta_exists($key) ?
            $order->update_meta_data($key, $value) : $order->add_meta_data(
                $key,
                $value,
                true
            );

        return $order->save() > 0;
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     * CRUD compatible.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getOrderMeta(
        WC_Abstract_Order $order,
        string $key
    ): string {
        return (string)$order->get_meta($key, true);
    }

    /**
     * Check if order was paid through Resurs Bank.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        return self::getPaymentId(order: $order) !== '';
    }

    /**
     * Retrieve order associated with payment id (CRUD compatible).
     */
    public static function getOrderByPaymentId(string $paymentId): ?WC_Order
    {
        $result = null;

        $orders = wc_get_orders(args: [
            'meta_key' => self::KEY_PAYMENT_ID,
            'meta_value' => $paymentId,
            'meta_compare' => '=',
            'limit' => 1,
        ]);

        if (!empty($orders) && $orders[0] instanceof WC_Order) {
            $result = $orders[0];
        }

        return $result;
    }

    /**
     * Check if order is from a legacy flow.
     */
    private static function isLegacyOrder(
        WC_Abstract_Order $order
    ): bool {
        return self::getOrderMeta(
            order: $order,
            key: self::KEY_LEGACY_ORDER_REFERENCE
        ) !== '';
    }

    /**
     * Attempts to use stored order reference on legacy orders to find
     */
    private static function findPaymentIdForLegacyOrder(
        WC_Abstract_Order $order
    ): string {
        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $orderReference = self::getOrderMeta(
                order: $order,
                key: self::KEY_LEGACY_ORDER_REFERENCE
            );
            $result = Repository::search(
                orderReference: $orderReference,
                storeId: UserSettingsRepository::getStoreId()
            );

            if ($result->count() > 0) {
                $payment = $result->getData()[0];

                if (!$payment instanceof Payment) {
                    throw new IllegalTypeException(
                        message: 'Fetched object type is ' .
                        get_class(object: $payment) .
                        ', expected ' . Payment::class
                    );
                }

                return $payment->id;
            }

            throw new EmptyValueException(
                message: 'No results found when searching for legacy order.'
            );
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }

        return '';
    }
}
