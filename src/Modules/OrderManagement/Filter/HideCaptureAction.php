<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Prevents the rendering of the button to complete an order on the order list view.
 */
class HideCaptureAction
{
    /**
     * Event listener.
     */
    public static function exec(
        array $actions,
        WC_Order $order
    ): array {
        $result = [];

        if (
            Metadata::isValidResursPayment(
                order: $order,
                checkPaymentStatus: false
            )
        ) {
            foreach ($actions as $name => $action) {
                try {
                    $canCapture = OrderManagement::canCapture(order: $order);
                } catch (Throwable) {
                    $canCapture = false;
                }

                if ($name === 'on_hold' || !$canCapture) {
                    continue;
                }

                $result[$name] = $action;
            }
        }

        return $result;
    }
}
