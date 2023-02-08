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
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Payment\Converter\Refund;
use Throwable;
use WC_Order_Refund;

/**
 * Contains code for handling order status change to "Refunded"
 */
class Refunded extends Status
{
    /**
     * Attempts to perform refund call to Resurs API.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws Throwable
     */
    public static function performRefund(int $orderId, int $refundId): void
    {
        $refundOrder = self::getRefundOrder(refundId: $refundId);

        $resursBankId = self::getResursBankId(orderId: $orderId);

        try {
            $resursBankPayment = Repository::get(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            return;
        }

        // Fetch items
        $items = self::getRefundItems(refundOrder: $refundOrder);

        // Confirm that we're not trying to refund too large an amount
        $refundableAmount = $resursBankPayment->order->capturedAmount - $resursBankPayment->order->refundedAmount;

        if ($refundOrder->get_amount() > $refundableAmount) {
            $errorMessage = 'Refund amount (' . $refundOrder->get_amount() . ') is too great, must be ' .
                            $refundableAmount . ' or less. Aborting refund.';
            MessageBag::addError(msg: $errorMessage);
            throw new IllegalValueException(message: $errorMessage);
        }

        // Perform refund operation
        try {
            if (sizeof($items->getData()) === 0) {
                Repository::refund(paymentId: $resursBankId);
            } else {
                Repository::refund(
                    paymentId: $resursBankId,
                    orderLines: $items
                );
            }
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to perform refund operation: ' . $error->getMessage() .
                     ', please verify order state manually.'
            );
            Config::getLogger()->error(message: $error);
        }
    }

    /**
     * Fetches the refund order object or throws an exception if this fails.
     *
     * @throws IllegalTypeException
     * @throws Throwable
     * @throws ConfigException
     */
    private static function getRefundOrder(int $refundId): WC_Order_Refund
    {
        try {
            $order = wc_get_order($refundId);

            if (!$order instanceof WC_Order_Refund) {
                if (is_object(value: $order)) {
                    $type = get_class(object: $order);
                } else {
                    $type = gettype(value: $order);
                }

                throw new IllegalTypeException(
                    message: 'wc_get_order returned object of type ' . $type .
                        ', WC_Order_Refund expected.'
                );
            }

            return $order;
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to load refund information. Aborting refund.'
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Fetch Resurs Bank payment reference.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws Throwable
     */
    private static function getResursBankId(int $orderId): string
    {
        try {
            $order = wc_get_order($orderId);

            $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

            if (!is_string(value: $resursBankId)) {
                MessageBag::addError(
                    msg: 'Unable to load Resurs Bank payment reference from order. Aborting refund.'
                );
                throw new IllegalTypeException(
                    message: 'Fetched Resurs Bank payment reference is not a string. Aborting refund.'
                );
            }

            if ($resursBankId === '') {
                MessageBag::addError(
                    msg: 'Unable to load Resurs Bank payment reference from order. Aborting refund.'
                );
                throw new IllegalValueException(
                    message: 'Fetched Resurs Bank payment reference is empty. Aborting refund.'
                );
            }

            return $resursBankId;
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to load order information. Aborting refund.'
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Fetch refund order lines.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws Throwable
     */
    private static function getRefundItems(WC_Order_Refund $refundOrder): OrderLineCollection
    {
        try {
            return Refund::getOrderLines(order: $refundOrder);
        } catch(Throwable $error) {
            MessageBag::addError(
                msg: 'Error encountered while attempting to fetch order lines. Aborting refund.'
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }
}
