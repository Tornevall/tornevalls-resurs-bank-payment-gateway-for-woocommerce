<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
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
     * @throws Throwable
     * @throws IllegalTypeException
     */
    public static function cancel(int $orderId, string $old): void
    {
        // Prepare WooCommerce order.
        $order = self::getWooCommerceOrder(orderId: $orderId);

        if (!Metadata::isValidResursPayment(order: $order)) {
            // If not ours, return silently.
            return;
        }

        $resursPaymentId = Metadata::getPaymentId(order: $order);

        try {
            $resursPayment = Repository::get(paymentId: $resursPaymentId);

            if (!$resursPayment->canCancel()) {
                $errorMessage = 'Resurs order can not be cancelled. Reverting to previous order status.';
                MessageBag::addError(msg: $errorMessage);
                // Throw own error based on prohibited action.
                throw new Exception(message: $errorMessage);
            }

            // On success, this is where order status and order notes are updated.
            // On failures, an exception will be thrown from here and order status will be reverted.
            self::performFullCancel(
                resursPaymentId: $resursPaymentId,
                order: $order,
                oldStatus: $old
            );
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for refund.'
            );
            Config::getLogger()->error(message: $error);
            // Throw error based on Resurs errors.
            throw $error;
        }
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
