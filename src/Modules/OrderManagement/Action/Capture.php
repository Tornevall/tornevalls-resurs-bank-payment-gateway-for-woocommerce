<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Exception;
use Resursbank\Ecom\Module\Payment\Repository;
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

        // Do not allow frozen orders to be captured from order list view, as
        // this can inaccurately trigger Modify.
        if ($payment->isFrozen() && Admin::isInOrderListView()) {
            // Will be caught upstream where we display an error message and log
            // as order comment as well.
            throw new Exception(message: 'Frozen orders cannot be captured from order list view.');
        }

        // @todo Can be moved to Ecom, to the Repo class perofmring capture. Use Payment History to track exact reason why capture cannot be executed.
        if (!$payment->canCapture()) {
            if ($payment->isCaptured()) {
                /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                $order->add_order_note(Translator::translate(phraseId: 'payment-already-captured'));
                return;
            }
            if ($payment->isFrozen()) {
                $order->add_order_note(Translator::translate(
                    phraseId: 'unable-to-capture-frozen-order'
                ));
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
