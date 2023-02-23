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
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Cancelled"
 */
class Cancelled extends Status
{
    /**
     * Cancel Resurs Bank payment.
     *
     * @throws ConfigException
     * @throws Throwable
     * @throws IllegalTypeException
     */
    public static function cancel(int $orderId): void
    {
        // Prepare WooCommerce order.
        $order = self::getWooCommerceOrder(orderId: $orderId);

        if (!self::isEnabled(order: $order)) {
            return;
        }

        $resursPaymentId = Metadata::getPaymentId(order: $order);

        try {
            $resursPayment = Repository::get(paymentId: $resursPaymentId);

            if (!$resursPayment->canCancel()) {
                $errorMessage = 'Resurs order can not be cancelled.';
                MessageBag::addError(msg: $errorMessage);
                // Throw own error based on prohibited action.
                throw new Exception(message: $errorMessage);
            }

            // On success, this is where order status and order notes are updated.
            // On failures, an exception will be thrown from here.
            self::performFullCancel(
                resursPaymentId: $resursPaymentId,
                order: $order
            );
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for cancel.'
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Performs the actual cancel operation.
     *
     * @throws ConfigException
     */
    private static function performFullCancel(string $resursPaymentId, WC_Order $order): void
    {
        try {
            Repository::cancel(paymentId: $resursPaymentId);
            $order->add_order_note(
                note: Translator::translate(phraseId: 'cancel-success')
            );
        } catch (Throwable $error) {
            $errorMessage = sprintf(
                'Unable to perform cancel order %s: %s.',
                $order->get_id(),
                $error->getMessage()
            );
            Config::getLogger()->error(message: $errorMessage);
            MessageBag::addError(msg: $errorMessage);
        }
    }

    /**
     * Whether payment can be cancelled.
     */
    private static function isEnabled(WC_Order $order): bool
    {
        return EnableCancel::isEnabled() &&
            Metadata::isValidResursPayment(order: $order);
    }
}
