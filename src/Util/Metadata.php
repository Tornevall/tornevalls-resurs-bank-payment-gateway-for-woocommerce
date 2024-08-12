<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

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
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Throwable;
use WC_Abstract_Order;
use WC_Order;

use function get_class;
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
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function setOrderMeta(
        WC_Abstract_Order $order,
        string $key,
        string $value
    ): bool {
        $exists = metadata_exists(
            'post',
            $order->get_id(),
            $key
        );

        if ($exists) {
            return (bool)update_post_meta(
                $order->get_id(),
                $key,
                $value
            );
        }

        return (bool)add_post_meta(
            $order->get_id(),
            $key,
            $value,
            true
        );
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getOrderMeta(
        WC_Abstract_Order $order,
        string $key
    ): string {
        return (string)get_post_meta(
            $order->get_id(),
            $key,
            true
        );
    }

    /**
     * Check if order was paid through Resurs Bank.
     */
    public static function isValidResursPayment(WC_Order|WC_Abstract_Order $order): bool
    {
        try {
            // Validate stored id first. No id = Not Resurs.
            // May occur in HPOS mode when orders are not in sync.
            self::getPaymentId(order: $order);
        } catch (Throwable) {
            return false;
        }

        try {
            self::getPaymentByTransient(order: $order);
            return true;
        } catch (Throwable $error) {
            Log::debug(message: $error->getMessage());
            return false;
        }
    }

    /**
     * Transient payment fetching for when we live in a store order list, rather than the direct order itself.
     *
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalValueException
     * @throws NotJsonEncodedException
     */
    public static function getPaymentByTransient(WC_Order $order): Payment
    {
        // Make sure we don't use transients in the single-order-view, but only in the big list.
        $useTransients = get_current_screen()->id === 'edit-shop_order';
        $transientKey = 'tmp_resurs_payment_' . $order->get_id();

        if ($useTransients) {
            $paymentInfo = get_transient(
                $transientKey
            ) ?: OrderManagement::getPayment(order: $order);
            set_transient($transientKey, $paymentInfo, 300);
        } else {
            $paymentInfo = OrderManagement::getPayment(order: $order);
        }

        return $paymentInfo;
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
