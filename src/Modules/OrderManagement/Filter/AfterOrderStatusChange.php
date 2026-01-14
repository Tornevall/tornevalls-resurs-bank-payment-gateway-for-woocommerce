<?php

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, SlevomatCodingStandard.Functions.UnusedParameter

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Cancel;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Capture;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Admin_Meta_Boxes;

/**
 * After order status has been changed, execute call to capture or cancel
 * payment at Resurs Bank (if matching order status is applied on order).
 */
class AfterOrderStatusChange
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws PaymentActionException
     */
    public static function exec(
        int $orderId,
        string $old,
        string $new
    ): void {
        try {
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
        } catch (Throwable $error) {
            Logger::error(message: $error);

            // Build pretty message for admin UI.
            $action = match ($new) {
                'completed' => 'capture',
                'cancelled' => 'cancel',
                default => null,
            };

            $parts = ['payment-action'];

            if ($action !== null) {
                $parts[] = $action;
            }

            $parts[] = 'failed';

            $msg = Translator::translate(
                phraseId: implode(separator: '-', array: $parts)
            );

            // Display message when page re-renders in admin.
            WC_Admin_Meta_Boxes::add_error(text: $msg);

            // Propagate exception to let WC log as order comment.
            //
            // This will create a generic comment which explains there was an
            // error while transitioning order status.
            //
            // If there is a problem with the after-shop action it's likely
            // going to be an API exception, in which case it will be tracked
            // by Payment History from Ecom as a separate order comment.
            throw new PaymentActionException(message: $msg);
        }
    }
}
