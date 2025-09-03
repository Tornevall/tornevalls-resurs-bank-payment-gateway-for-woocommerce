<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Exception;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic to capture Resurs Bank payment.
 */
class Capture extends Action
{
    /**
     * Capture Resurs Bank payment.
     * @phpcs:ignoreFile CognitiveComplexity
     * @throws Throwable
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function exec(
        WC_Order $order
    ): void {
        if (!EnableCapture::isEnabled()) {
            return;
        }

        OrderManagement::execAction(
            action: ActionType::CAPTURE,
            order: $order,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(order: $order);

                $frozenPreventionMessage = Translator::translate(
                    phraseId: 'unable-to-capture-frozen-order'
                );

                // Do not allow frozen orders to be captured from order list view, as this
                // could trigger Modify, which we normally don't want.
                if ($payment->isFrozen() && Admin::isInOrderListView()) {
                    // Trying to scream on screen when this occurs.
                    OrderManagement::logActionError(
                        action: ActionType::CAPTURE,
                        order: $order,
                        error: new Exception(message: $frozenPreventionMessage),
                        reason: $frozenPreventionMessage
                    );

                    throw new Exception(message: $frozenPreventionMessage);
                }

                if (!$payment->canCapture()) {
                    if ($payment->isCaptured()) {
                        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                        $order->add_order_note(Translator::translate(phraseId: 'payment-already-captured'));
                        return;
                    }
                    if ($payment->isFrozen()) {
                        $order->add_order_note($frozenPreventionMessage);
                    }
                    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                    $order->add_order_note(Translator::translate(phraseId: 'payment-not-ready-to-be-captured'));
                    return;
                }
                $authorizedAmount = number_format(
                    num: (float)$payment->order?->authorizedAmount,
                    decimals: 2,
                    decimal_separator: '.',
                    thousands_separator: ''
                );
                $orderTotal = number_format(
                    num: (float)$order->get_total(),
                    decimals: 2,
                    decimal_separator: '.',
                    thousands_separator: ''
                );

                if ($authorizedAmount !== $orderTotal) {
                    $mismatchError = Translator::translate(
                        phraseId: 'debitable-amount-does-not-match-authorized-amount'
                    );

                    if (Admin::isInOrderListView()) {
                        $mismatchError = '[Order: ' . $order->get_id() . '] ' . $mismatchError;
                    }

                    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                    $order->add_order_note($mismatchError);
                    MessageBag::addError(message: $mismatchError);

                    if (Admin::isInOrderListView() || is_ajax()) {
                        throw new Exception(message: $mismatchError);
                    }
                }

                $transactionId = self::generateTransactionId();

                $response = Repository::capture(
                    paymentId: $payment->id,
                    transactionId: $transactionId
                );

                $action = $response->order?->actionLog->getByTransactionId(
                    id: $transactionId
                );

                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::CAPTURE,
                    order: $order,
                    amount: $action?->orderLines->getTotal()
                );
            }
        );
    }
}
