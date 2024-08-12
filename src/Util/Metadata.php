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
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
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
    public const KEY_THANK_YOU = RESURSBANK_MODULE_PREFIX . '_thankyou_trigger';

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
     * @throws EmptyValueException
     */
    public static function getPaymentId(WC_Abstract_Order $order): string
    {
        $paymentId = self::getOrderMeta(
            order: $order,
            key: self::KEY_PAYMENT_ID
        );

        if ($paymentId === '' && self::isLegacyOrder(order: $order)) {
            $paymentId = self::findPaymentIdForLegacyOrder(order: $order);

            if ($paymentId === '') {
                throw new EmptyValueException(
                    message: 'No results found when searching for legacy order.'
                );
            }

            self::setOrderMeta(
                order: $order,
                key: self::KEY_PAYMENT_ID,
                value: $paymentId
            );
        }

        if ($paymentId === '') {
            throw new EmptyValueException(
                message: 'Unable to fetch payment ID'
            );
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
        if ($order->meta_exists($key)) {
            $order->update_meta_data($key, $value);
        } else {
            $order->add_meta_data($key, $value, true);
        }

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
     */
    public static function isValidResursPayment(WC_Order $order, bool $checkPaymentStatus = true): bool
    {
        global $resursPaymentValidation;

        try {
            // Attempt to retrieve the payment ID; if it fails, the order is invalid.
            self::getPaymentId(order: $order);
        } catch (Throwable) {
            return false;
        }

        $orderId = $order->get_id();

        // If checkPaymentStatus is true, attempt to validate the payment.
        if ($checkPaymentStatus && !isset($resursPaymentValidation[$orderId])) {
            try {
                OrderManagement::getPayment(order: $order);
                $resursPaymentValidation[$orderId] = true;
            } catch (Throwable $error) {
                Log::debug(message: $error->getMessage());
                $resursPaymentValidation[$orderId] = false;
                return false;
            }
        }

        // If all checks passed or if checkPaymentStatus has not been requested.
        return $resursPaymentValidation[$orderId] ?? true;
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
                storeId: StoreId::getData(),
                orderReference: $orderReference
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
            Log::error(error: $error);
        }

        return '';
    }
}
