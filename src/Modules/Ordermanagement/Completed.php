<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Completed"
 */
class Completed extends Status
{
    /**
     * Perform capture of Resurs payment.
     *
     * @param int $orderId WooCommerce order ID
     * @throws ConfigException
     */
    public static function capture(int $orderId, string $old): void
    {
        try {
            $order = self::getWooCommerceOrder(orderId: $orderId);
        } catch (Throwable) {
            return;
        }

        $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

        if (empty($resursBankId)) {
            return;
        }

        try {
            $resursPayment = self::getResursPayment(
                paymentId: $resursBankId,
                order: $order,
                oldStatus: $old
            );
        } catch (Throwable) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for capture. Reverting to previous order status.'
            );
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
