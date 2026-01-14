<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\NotJsonEncodedException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order;
use WC_Order_Refund;

/**
 * Business logic to refund Resurs Bank payment.
 */
class Refund extends Action
{
    /**
     * Refund Resurs Bank payment.
     *
     * @param WC_Order $order
     * @param WC_Order_Refund $refund
     * @throws IllegalTypeException
     * @throws PaymentActionException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @throws NotJsonEncodedException
     */
    public static function exec(
        WC_Order $order,
        WC_Order_Refund $refund
    ): void {
        $payment = OrderManagement::getPayment(order: $order);

        if (!$payment->canRefund()) {
            return;
        }

        $orderLines = Order::getOrderLines(order: $refund);

        $amount = $refund->get_amount();

        if (!is_numeric(value: $amount)) {
            throw new IllegalTypeException(
                message: 'Refund amount is not numeric.'
            );
        }

        $availableAmount = $payment->order->capturedAmount - $payment->order->refundedAmount;

        // @todo Validation process should be moved to Ecom.
        if ((float) $amount > $availableAmount) {
            throw new PaymentActionException(
                message: sprintf(
                    Translator::translate(phraseId: 'refund-too-large'),
                    $amount,
                    $availableAmount
                )
            );
        }

        $transactionId = self::generateTransactionId();

        Repository::refund(
            paymentId: $payment->id,
            orderLines: $orderLines->count() > 0 ? $orderLines : null,
            transactionId: $transactionId
        );
    }
}
