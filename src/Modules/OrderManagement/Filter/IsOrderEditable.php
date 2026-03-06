<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\OrderManagement\Filter;

use Resursbankabpayments\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbankabpayments\Woocommerce\Util\Log;
use Resursbankabpayments\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Prevents rendering options to edit order, if the payment at Resurs Bank can
 * no longer be modified.
 */
class IsOrderEditable
{
    /**
     * Event listener.
     */
    public static function exec(
        bool $result,
        WC_Order $order
    ): bool {
        if (!Metadata::isValidResursPayment(order: $order)) {
            return $result;
        }

        try {
            $result = OrderManagement::canEdit(order: $order);
        } catch (Throwable $error) {
            Log::error(error: $error);
            $result = false;
        }

        return $result;
    }
}
