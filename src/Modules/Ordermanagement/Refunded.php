<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Refunded"
 */
class Refunded extends Status
{
    /**
     * Performs full refund of Resurs payment.
     *
     * @throws ConfigException
     */
    public static function refund(int $orderId, string $old): void
    {
        try {
            $order = self::getWooCommerceOrder(orderId: $orderId);
        } catch (Throwable) {
            return;
        }

        $resursPaymentId = $order->get_meta(key: 'resursbank_payment_id');

        if (empty($resursPaymentId)) {
            return;
        }

        try {
            $resursPayment = self::updateOrderStatus(
                paymentId: $resursPaymentId,
                order: $order,
                oldStatus: $old
            );
        } catch (Throwable) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for refund. Reverting to previous order status.'
            );
            return;
        }

        if (!$resursPayment->canRefund()) {
            MessageBag::addError(
                msg: 'Resurs order can not be refunded. Reverting to previous order status.'
            );
            $order->update_status(new_status: $old);
            return;
        }

        self::performFullRefund(
            resursPaymentId: $resursPaymentId,
            order: $order,
            oldStatus: $old
        );
    }

    /**
     * Performs the actual refund operation.
     *
     * @throws ConfigException
     */
    private static function performFullRefund(string $resursPaymentId, WC_Order $order, string $oldStatus): void
    {
        try {
            Repository::refund(paymentId: $resursPaymentId);
            $order->add_order_note(
                note: Translator::translate(phraseId: 'refund-success')
            );
        } catch (Throwable $error) {
            $errorMessage = sprintf(
                "Unable to perform refund order %s: %s. Reverting to previous order status",
                $order->get_id(),
                $error->getMessage()
            );
            Config::getLogger()->error(message: $error);
            MessageBag::addError(msg: $errorMessage);
            $order->update_status(new_status: $oldStatus, note: $errorMessage);
        }
    }
}
