<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Exception\Validation\MissingValueException;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Woocommerce\Modules\OrderManagement\Action\Refund as RefundAction;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Admin_Meta_Boxes;
use WC_Order_Refund;

/**
 * Event triggered when a refund is applied on the order (partial refund), or
 * the order status is changed to "Refunded" (full).
 */
class Refund
{
    /**
     * Event listener.
     *
     * @todo Consider adding order notes if possible. Right now we display an error message, in most other places we also add order notes.
     */
    public static function exec(int $orderId, int $refundId): void
    {
        try {
            $order = OrderManagement::getOrder(id: $orderId);

            if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
                return;
            }

            $refund = wc_get_order(the_order: $refundId);

            if (!$refund instanceof WC_Order_Refund) {
                throw new MissingValueException(
                    message: 'Returned object not of type WC_Order_Refund'
                );
            }

            RefundAction::exec(order: $order, refund: $refund);
        } catch (PaymentActionException $error) {
            WC_Admin_Meta_Boxes::add_error(text: $error->getMessage());
        } catch (MissingValueException $error) {
            Logger::error(message: $error);

            // Specific error if we fail to resolve the refund object.
            WC_Admin_Meta_Boxes::add_error(
                text: sprintf(
                    Translator::translate(phraseId: 'failed-resolving-refund'),
                    $refundId
                )
            );
        } catch (Throwable $error) {
            Logger::error(message: $error);

            // Generic error for all other issues during refund process.
            WC_Admin_Meta_Boxes::add_error(
                text: Translator::translate(phraseId: 'payment-action-refund-failed')
            );
        }
    }
}
