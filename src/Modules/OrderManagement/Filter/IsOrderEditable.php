<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
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
            $payment = OrderManagement::getPayment(order: $order);
            $result = $payment->canModify();

            if (!$result) {
                // If we've rejected or frozen the payment, we want to reflect
                // in the administration interface that this is the reason why
                // the order is not editable.
                //
                // This is to avoid confusion why an order cannot be edited
                // sometimes even if the status of it would allow modifications.
                //
                // For example, WooCommerce allows you to edit orders that are
                // "on-hold", but we use this status to indicate that the payment
                // has been frozen at Resurs Bank, and thus cannot be modified.
                add_filter(
                    'gettext',
                    static function ($translation, $text, $domain) use ($payment) {

                        if (
                            isset($text) &&
                            $text === 'This order is no longer editable.'
                        ) {
                            if ($payment->isRejected()) {
                                $translation = Translator::translate(
                                    phraseId: 'can-not-edit-order-due-to-rejected'
                                );
                            }

                            if ($payment->isFrozen()) {
                                $translation = Translator::translate(
                                    phraseId: 'can-not-edit-order-due-to-frozen'
                                );
                            }
                        }

                        return $translation;
                    },
                    999,
                    3
                );
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
            $result = false;
        }

        return $result;
    }
}
