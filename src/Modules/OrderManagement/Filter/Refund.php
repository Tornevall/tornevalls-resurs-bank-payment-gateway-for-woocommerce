<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Refund as RefundAction;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order_Refund;

/**
 * Event triggered when a refund is applied on the order (partial refund), or
 * the order status is changed to "Refunded" (full).
 */
class Refund
{
    /**
     * Event listener.
     */
    public static function exec(int $orderId, int $refundId): void
    {
        $order = OrderManagement::getOrder(id: $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        $refund = self::getRefund(id: $refundId);

        if ($refund === null) {
            return;
        }

        RefundAction::exec(order: $order, refund: $refund);
    }

    /**
     * Resolve refund object.
     */
    private static function getRefund(int $id): ?WC_Order_Refund
    {
        $result = null;

        try {
            $result = wc_get_order(the_order: $id);

            if (!$result instanceof WC_Order_Refund) {
                throw new IllegalTypeException(
                    message: 'Returned object not of type WC_Order_Refund'
                );
            }
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: sprintf(
                    Translator::translate(phraseId: 'failed-resolving-refund'),
                    $id
                )
            );
        }

        return $result;
    }
}
