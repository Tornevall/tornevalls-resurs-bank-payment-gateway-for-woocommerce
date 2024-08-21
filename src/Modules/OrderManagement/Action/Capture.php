<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order;

/**
 * Business logic to capture Resurs Bank payment.
 */
class Capture extends Action
{
    /**
     * Capture Resurs Bank payment.
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
                $authorizedAmount = $payment->order?->authorizedAmount;

                if (!$payment->canCapture()) {
                    return;
                }

                if ((float)$authorizedAmount !== (float)$order->get_total()) {
                    $mismatchError = Translator::translate(
                        phraseId: 'debitable-amount-does-not-match-authorized-amount'
                    );

                    if (Admin::isInOrderListView()) {
                        $mismatchError = '[Order: ' . $order->get_id() . '] ' . $mismatchError;
                    }

                    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                    $order->add_order_note($mismatchError);
                    MessageBag::addError(message: $mismatchError);
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
