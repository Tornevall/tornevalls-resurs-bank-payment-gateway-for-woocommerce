<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Completed"
 */
class Completed
{
    /**
     * Perform capture of Resurs payment.
     *
     * @param int $orderId WooCommerce order ID
     * @throws ConfigException
     */
    public static function capture(int $orderId): void
    {
        /** @var WC_Order $order */
        $order = wc_get_order(the_order: $orderId);

        $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

        if (empty($resursBankId)) {
            return;
        }

        try {
            $resursPayment = Repository::get(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for capture.'
            );
            return;
        }

        if (!$resursPayment->canCapture()) {
            MessageBag::addError(msg: 'Resurs order can not be captured.');
            return;
        }

        try {
            Repository::capture(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(
                msg: 'Unable to perform capture: ' . $error->getMessage()
            );
        }
    }
}
