<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Exception;
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
use Resursbank\Woocommerce\Util\Metadata;
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
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public static function capture(int $orderId, string $old): void
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

            if (!$resursPayment->canCapture()) {
                $errorMessage = 'Resurs order can not be captured. Reverting to previous order status.';
                MessageBag::addError(msg: $errorMessage);
                throw new Exception(message: $errorMessage);
            }

            self::performCapture(
                resursPaymentId: $resursPaymentId,
                order: $order,
                oldStatus: $old
            );
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for capture.'
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Performs the actual capture.
     *
     * @throws ConfigException
     */
    private static function performCapture(string $resursPaymentId, WC_Order $order, string $oldStatus): void
    {
        try {
            Repository::capture(paymentId: $resursPaymentId);
            $order->add_order_note(
                note: Translator::translate(phraseId: 'capture-success')
            );
        } catch (Throwable $error) {
            $errorMessage = sprintf(
                'Unable to perform capture order %s: %s. Reverting to previous order status',
                $order->get_id(),
                $error->getMessage()
            );
            Config::getLogger()->error(message: $error);
            MessageBag::addError(msg: $errorMessage);
            $order->update_status(new_status: $oldStatus, note: $errorMessage);
        }
    }
}
