<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Woocommerce\Util\Metadata;
use WC_Order;

/**
 * Prevents the rendering of the button to complete an order on the order list view.
 */
class HideCaptureAction
{
    /**
     * Event listener.
     * @phpcs:ignoreFile CognitiveComplexity
     */
    public static function exec(
        array $actions,
        WC_Order $order
    ): array {
        $result = [];

        if (Metadata::isValidResursPayment(order: $order)) {
            foreach ($actions as $name => $action) {
                // Prevent the "complete" button from being added if the order or action is "on-hold".
                // Also, don't allow changing an "on-hold" order to "processing" in the list view,
                // as it might be frozen by Resurs. Forcing a status change could cause incorrect handling
                // and potential cancellation of the order. Actions are limited to not block for other
                // buttons.
                /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                if ($name === 'on_hold' ||
                    ($order->has_status('on-hold') && ($name === 'complete' || $name === 'processing'))
                ) {
                    continue;
                }

                $result[$name] = $action;
            }
        }

        return $result;
    }
}
