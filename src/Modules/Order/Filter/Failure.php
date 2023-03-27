<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

use function is_string;

/**
 * Event executed when failure page is reached.
 */
class Failure
{
    /**
     * Register event listener.
     */
    public static function init(): void
    {
        add_filter(
            hook_name: 'woocommerce_order_cancelled_notice',
            callback: 'Resursbank\Woocommerce\Modules\Order\Filter\Failure::exec',
            priority: 10,
            accepted_args: 1
        );
    }

    /**
     * Add information to message on order failure page, explaining why payment
     * failed at Resurs Bank.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function exec(string $message = ''): string
    {
        $orderId = $_GET['order_id'] ?? '';

        if (!is_string(value: $orderId) || $orderId === '') {
            return $message;
        }

        $order = OrderManagement::getOrder(id: (int)$orderId);

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            if ($order === null) {
                throw new IllegalValueException(message: 'Missing order id.');
            }

            $paymentId = Metadata::getPaymentId(order: $order);
            $task = Repository::getTaskStatusDetails(paymentId: $paymentId);

            $message .= ' ';
            $message .= $task->completed ?
                Translator::translate(phraseId: 'payment-failed-try-again') :
                Translator::translate(phraseId: 'payment-cancelled-try-again');
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $message;
    }
}
