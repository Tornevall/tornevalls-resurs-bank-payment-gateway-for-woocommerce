<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Cancelled"
 */
class Cancelled extends Status
{
    /**
     * Performs full refund of Resurs payment.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
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
            $cancelResponse = Repository::cancel(paymentId: $resursPaymentId);
            $order->add_order_note(
                note: Translator::translate(phraseId: 'cancel-success')
            );
            OrderModule::setConfirmedAmountNote(
                actionType: 'Captured ',
                order: $order,
                resursPayment: $cancelResponse
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
