<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

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
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Order status handler. Centralization for received callbacks and user interactions via thankyou..
 */
class OrderStatus
{
    /**
     * @param WC_Order $order WooCommerce order.
     * @param string $paymentId Resurs payment id (uuid based).
     * @return void
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
     * @noinspection PhpUnhandledExceptionInspection
     */
    public static function setWcOrderStatus(WC_Order $order, string $paymentId): void
    {
        // Try-catch should not be placed here, as any exceptions will be caught at the json-response level.
        $resursPayment = PaymentRepository::get(paymentId: $paymentId);

        // Silently handle statuses.
        if (!$order->has_status(status: ['on-hold', 'processing', 'completed', 'cancelled'])) {
            match ($resursPayment->status) {
                PaymentStatus::ACCEPTED => $order->payment_complete(),
                PaymentStatus::REJECTED => $order->update_status(
                    new_status: 'failed',
                    note: 'Payment rejected by Resurs.'
                ),
                default => $order->update_status(
                    new_status: 'on-hold',
                    note: 'Payment is waiting for more information from Resurs.'
                ),
            };
        }
    }

    /**
     * Handle the landing-page from within the payment method gateway as the "thank you page" is very much
     * a dynamic request. It either depends on the payment method (uuid) through the "thank_you_<id>" action or
     * the single action ("thank_you", which is what we use), that is just firing thank-you's with the current order id.
     *
     * @param $order_id
     * @return void
     * @throws ConfigException
     */
    public static function setOrderStatusOnThankYouSuccess($order_id = null): void
    {
        try {
            $order = new WC_Order($order_id);
            $resursPaymentId = Metadata::getOrderMeta(order: $order, metaDataKey: 'payment_id');
            $thankYouTriggerCheck = (bool)Metadata::getOrderMeta(order: $order, metaDataKey: 'thankyou_trigger');
            if ($thankYouTriggerCheck || $resursPaymentId === '') {
                // Not ours or already triggered.
                return;
            }
            // Record that customer landed on the thank-you page once, so we don't have to run
            // twice if page is reloaded.
            Metadata::setOrderMeta(order: $order, metaDataKey: 'thankyou_trigger', metaDataValue: '1');
            // This visually marks a proper customer return, from an external source.
            $order->add_order_note(note: 'Customer returned from external source.');
            OrderStatus::setWcOrderStatus(order: $order, paymentId: $resursPaymentId);
        } catch (Throwable $e) {
            // Nothing happens here, except for logging.
            Config::getLogger()->error(message: $e);
        }
    }
}
