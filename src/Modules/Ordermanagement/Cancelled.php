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
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Cancelled"
 */
class Cancelled extends Status
{
    /**
     * Cancel full refund of Resurs payment.
     *
     * @throws ConfigException
     */
    public static function cancel(int $orderId, string $old): void
    {
        try {
            $order = self::getWooCommerceOrder(orderId: $orderId);
        } catch (Throwable) {
            return;
        }

        $resursPaymentId = Metadata::getPaymentId(order: $order);

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

        if (!$resursPayment->canCancel()) {
            MessageBag::addError(
                msg: 'Resurs order can not be refunded. Reverting to previous order status.'
            );
            $order->update_status(new_status: $old);
            return;
        }

        self::performFullCancel(
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
    private static function performFullCancel(string $resursPaymentId, WC_Order $order, string $oldStatus): void
    {
        try {
            Repository::cancel(paymentId: $resursPaymentId);
            $order->add_order_note(
                note: Translator::translate(phraseId: 'cancel-success')
            );
        } catch (Throwable $error) {
            $errorMessage = sprintf(
                'Unable to perform cancel order %s: %s. Reverting to previous order status',
                $order->get_id(),
                $error->getMessage()
            );
            Config::getLogger()->error(message: $errorMessage);
            MessageBag::addError(msg: $errorMessage);
            $order->update_status(new_status: $oldStatus, note: $errorMessage);
        }
    }
}
