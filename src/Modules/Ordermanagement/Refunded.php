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
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Payment\Converter\Refund;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;
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
     * @noinspection PhpUnused
     */
    public static function performRefund(int $orderId, int $refundId): void
    {
        if (!self::isResursPayment(orderId: $orderId)) {
            return;
        }

        $refundOrder = self::getRefundOrder(refundId: $refundId);

        $resursBankId = self::getResursBankId(orderId: $orderId);

        $resursBankPayment = self::getResursBankPayment(
            resursBankPaymentId: $resursBankId
        );

        $items = self::getRefundItems(refundOrder: $refundOrder);

        $refundableAmount = $resursBankPayment->order->capturedAmount - $resursBankPayment->order->refundedAmount;

        if ($refundOrder->get_amount() > $refundableAmount) {
            $errorMessage = str_replace(
                search: ['%1', '%2'],
                replace: [
                    $refundOrder->get_amount(),
                    $refundableAmount,
                ],
                subject: Translator::translate(phraseId: 'refund-too-large')
            );
            MessageBag::addError(msg: $errorMessage);
            throw new IllegalValueException(message: $errorMessage);
        }

        self::performActualRefund(
            items: $items,
            resursBankPaymentId: $resursBankId
        );
    }

    /**
     * Perform the actual refund operation.
     *
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
     * @throws Throwable
     * @throws TranslationException
     * @throws ValidationException
     */
    private static function performActualRefund(
        OrderLineCollection $items,
        string $resursBankPaymentId
    ): void {
        try {
            if (sizeof($items->getData()) === 0) {
                Repository::refund(paymentId: $resursBankPaymentId);
            } else {
                Repository::refund(
                    paymentId: $resursBankPaymentId,
                    orderLines: $items
                );
            }
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: str_replace(
                    search: ['%1'],
                    replace: [
                        $error->getMessage(),
                    ],
                    subject: Translator::translate(
                        phraseId: 'refund-unable-to-perform-operation'
                    )
                )
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Wrapper method for fetching Resurs payment object.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ApiException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     */
    private static function getResursBankPayment(string $resursBankPaymentId): Payment
    {
        try {
            return Repository::get(paymentId: $resursBankPaymentId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }

    /**
     * Wrapper for Metadata::isValidResursPayment.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     */
    private static function isResursPayment(int $orderId): bool
    {
        $order = wc_get_order(the_order: $orderId);

        if (!$order instanceof WC_Order) {
            throw new IllegalTypeException(
                message: Translator::translate(
                    phraseId: 'refund-unable-to-load-order-information'
                )
            );
        }

        return Metadata::isValidResursPayment(order: $order);
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
            $order = wc_get_order(the_order: $refundId);

            if (!$order instanceof WC_Order_Refund) {
                $type = is_object(value: $order) ? get_class(
                    object: $order
                ) : gettype(value: $order);

                throw new IllegalTypeException(
                    message: str_replace(
                        search: ['%1'],
                        replace: [
                            $type,
                        ],
                        subject: Translator::translate(
                            phraseId: 'refund-wrong-return-type-from-wc-get-order'
                        )
                    )
                );
            }

            return $order;
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: Translator::translate(
                    phraseId: 'refund-unable-to-load-refund-information'
                )
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
                    msg: Translator::translate(
                        phraseId: 'refund-payment-reference-not-a-string'
                    )
                );
                throw new IllegalTypeException(
                    message: Translator::translate(
                        phraseId: 'refund-payment-reference-not-a-string'
                    )
                );
            }

            if ($resursBankId === '') {
                MessageBag::addError(
                    msg: Translator::translate(
                        phraseId: 'refund-payment-reference-is-empty'
                    )
                );
                throw new IllegalValueException(
                    message: Translator::translate(
                        phraseId: 'refund-payment-reference-is-empty'
                    )
                );
            }

            return $resursBankId;
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: Translator::translate(
                    phraseId: 'refund-unable-to-load-order-information'
                )
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
        } catch (Throwable $error) {
            MessageBag::addError(
                msg: Translator::translate(
                    phraseId: 'refund-error-when-fetching-order-lines'
                )
            );
            Config::getLogger()->error(message: $error);
            throw $error;
        }
    }
}
