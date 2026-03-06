<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\OrderManagement\Filter;

use Resursbankabpayments\Woocommerce\Modules\OrderManagement\Action\Cancel;
use Resursbankabpayments\Woocommerce\Modules\OrderManagement\Action\Capture;
use Resursbankabpayments\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbankabpayments\Woocommerce\Util\Metadata;

/**
 * After order status has been changed, execute call to capture or cancel
 * payment at Resurs Bank (if matching order status is applied on order).
 */
class AfterOrderStatusChange
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public static function exec(
        int $orderId,
        string $old,
        string $new
    ): void {
        $order = OrderManagement::getOrder(id: $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        switch ($new) {
            case 'completed':
                Capture::exec(order: $order);
                break;

            case 'cancelled':
                Cancel::exec(order: $order);
                break;

            default:
                break;
        }
    }
}
