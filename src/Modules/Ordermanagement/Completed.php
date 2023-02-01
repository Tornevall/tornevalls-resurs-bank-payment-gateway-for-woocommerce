<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Completed"
 */
class Completed
{
    /**
     * Perform capture of Resurs payment.
     *
     * @param int $orderId WooCommerce order ID
     * @throws ConfigException
     */
    public static function capture(int $orderId, string $old, string $new): void
    {
        if ($new !== 'completed') {
            return;
        }

        try {
            $order = self::getWooCommerceOrder(orderId: $orderId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(msg: $error->getMessage());
            return;
        }

        $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

        if (empty($resursBankId)) {
            return;
        }

        try {
            $resursPayment = Repository::get(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for capture. Reverting to previous order status.'
            );
            $order->update_status(new_status: $old);
            return;
        }

        if (!$resursPayment->canCapture()) {
            MessageBag::addError(
                msg: 'Resurs order can not be captured. Reverting to previous order status.'
            );
            $order->update_status(new_status: $old);
            return;
        }

        self::performCapture(
            resursBankId: $resursBankId,
            order: $order,
            oldStatus: $old
        );
    }

    /**
     * Type-safely fetch WooCommerce order object.
     *
     * @throws IllegalTypeException
     */
    private static function getWooCommerceOrder(int $orderId): WC_Order
    {
        $order = wc_get_order(the_order: $orderId);

        if (!$order instanceof WC_Order) {
            throw new IllegalTypeException(
                message: 'Returned object not of type WC_Order'
            );
        }

        return $order;
    }

    /**
     * Performs the actual capture.
     *
     * @throws ConfigException
     */
    private static function performCapture(string $resursBankId, WC_Order $order, string $oldStatus): void
    {
        try {
            Repository::capture(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(
                msg: 'Unable to perform capture: ' . $error->getMessage() . '. Reverting to previous order status'
            );
            $order->update_status(new_status: $oldStatus);
        }
    }
}
