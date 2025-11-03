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
     */
    public static function exec(
        WC_Order $order
    ): void {
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
                error: new Exception(message: $frozenPreventionMessage),
                reason: $frozenPreventionMessage
            );
            return;
        }

        if (!$payment->canCapture()) {
            if ($payment->isCaptured()) {
                /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                $order->add_order_note(Translator::translate(phraseId: 'payment-already-captured'));
                return;
            }
            if ($payment->isFrozen()) {
                $order->add_order_note($frozenPreventionMessage);
                return;
            }
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            $order->add_order_note(Translator::translate(phraseId: 'payment-not-ready-to-be-captured'));
            return;
        }

        $transactionId = self::generateTransactionId();

        Repository::capture(
            paymentId: $payment->id,
            transactionId: $transactionId
        );
    }
}
