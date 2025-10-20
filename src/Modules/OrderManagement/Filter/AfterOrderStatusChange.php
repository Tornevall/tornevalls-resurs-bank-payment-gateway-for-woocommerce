<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Cancel;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Capture;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;

/**
 * After order status has been changed, execute call to capture or cancel
 * payment at Resurs Bank (if matching order status is applied on order).
 */
class AfterOrderStatusChange
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws ConfigException
     * @throws UserSettingsException
     * @throws Throwable
     */
    public static function exec(
        int $orderId,
        string $old,
        string $new
    ): void {
        $settings = Repository::getSettings();

        $order = OrderManagement::getOrder(id: $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        switch ($new) {
            case 'completed':
                if ($settings->captureEnabled) {
                    Capture::exec(order: $order);
                }

                break;

            case 'cancelled':
                if ($settings->cancelEnabled) {
                    Cancel::exec(order: $order);
                }

                break;

            default:
                break;
        }
    }
}
