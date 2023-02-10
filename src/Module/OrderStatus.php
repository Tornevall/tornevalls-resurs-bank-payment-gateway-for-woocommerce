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
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Payment;
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
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @noinspection PhpUnhandledExceptionInspection
     */
    public static function setWcOrderStatus(WC_Order $order, string $paymentId): void
    {
        // Try-catch should not be placed here, as any exceptions will be caught at the json-response level.
        $resursPayment = PaymentRepository::get(paymentId: $paymentId);

        // If order is held, check if prior order statuses was frozen.
        if (
            $order->has_status(status: ['on-hold']) &&
            Metadata::getOrderMeta(
                order: $order,
                metaDataKey: 'resurs_hold'
            ) === '1'
        ) {
            self::handleFrozenConditions(
                order: $order,
                resursPayment: $resursPayment
            );
        }

        // Silently handle statuses.
        if (
            $order->has_status(
                status: ['on-hold', 'processing', 'completed', 'cancelled']
            )
        ) {
            return;
        }

        // Mark order as held, if held from Resurs.
        if (
            $resursPayment->status === PaymentStatus::FROZEN ||
            $resursPayment->status === PaymentStatus::INSPECTION
        ) {
            Metadata::setOrderMeta(
                order: $order,
                metaDataKey: 'resurs_hold',
                metaDataValue: '1'
            );
        }

        match ($resursPayment->status) {
            PaymentStatus::ACCEPTED => $order->payment_complete(),
            PaymentStatus::REJECTED => $order->update_status(
                new_status: 'failed',
                note: Translator::translate(phraseId: 'payment-status-failed')
            ),
            default => $order->update_status(
                new_status: 'on-hold',
                note: Translator::translate(phraseId: 'payment-status-on-hold')
            ),
        };
    }

    /**
     * Handle the landing-page from within the payment method gateway as the "thank you page" is very much
     * a dynamic request. It either depends on the payment method (uuid) through the "thank_you_<id>" action or
     * the single action ("thank_you", which is what we use), that is just firing thank-you's with the current order id.
     *
     * @param $order_id
     * @throws ConfigException
     */
    public static function setOrderStatusOnThankYouSuccess($order_id = null): void
    {
        try {
            $order = new WC_Order(order: $order_id);
            $resursPaymentId = Metadata::getPaymentId(order: $order);
            $thankYouTriggerCheck = (bool)Metadata::getOrderMeta(
                order: $order,
                metaDataKey: 'thankyou_trigger'
            );

            if ($thankYouTriggerCheck || $resursPaymentId === '') {
                // Not ours or already triggered.
                return;
            }

            // Record that customer landed on the thank-you page once, so we don't have to run
            // twice if page is reloaded.
            Metadata::setOrderMeta(
                order: $order,
                metaDataKey: 'thankyou_trigger',
                metaDataValue: '1'
            );
            // This visually marks a proper customer return, from an external source.
            $order->add_order_note(
                note: Translator::translate(
                    phraseId: 'customer-landingpage-return'
                )
            );
            OrderStatus::setWcOrderStatus(
                order: $order,
                paymentId: $resursPaymentId
            );
        } catch (Throwable $e) {
            // Nothing happens here, except for logging.
            Config::getLogger()->error(message: $e);
        }
    }

    /**
     * Thaw or cancel an order that has been marked as held by Resurs.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     */
    private static function handleFrozenConditions(WC_Order $order, Payment $resursPayment): void
    {
        if ($resursPayment->status === PaymentStatus::REJECTED) {
            $order->update_status(
                new_status: 'failed',
                note: Translator::translate(phraseId: 'payment-status-failed')
            );
        }

        if ($resursPayment->status !== PaymentStatus::ACCEPTED) {
            return;
        }

        $order->payment_complete();
        Metadata::setOrderMeta(
            order: $order,
            metaDataKey: 'resurs_hold',
            metaDataValue: '0'
        );
    }
}
