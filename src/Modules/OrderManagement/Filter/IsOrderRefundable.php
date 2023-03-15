<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Prevents rendering options to refund order, if the payment at Resurs Bank can
 * no longer be refunded.
 */
class IsOrderRefundable
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function exec(
        bool $result,
        int $orderId,
        WC_Order $order
    ): bool {
        if (!Metadata::isValidResursPayment(order: $order)) {
            return $result;
        }

        try {
            $result = OrderManagement::canRefund(order: $order);
        } catch (Throwable $error) {
            Log::error(error: $error);
            $result = false;
        }

        return $result;
    }
}
