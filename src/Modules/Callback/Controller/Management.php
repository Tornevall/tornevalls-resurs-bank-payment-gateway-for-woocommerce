<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback\Controller;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Callback\Http\ManagementController;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Modules\Callback\Callback as CallbackModule;
use Resursbank\Woocommerce\Util\Metadata;
use WC_Order;

/**
 * Process management callback (occurs after checkout has completed).
 */
class Management extends ManagementController
{
    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public function updateOrderStatus(
        WC_Order $order
    ): void {
        $payment = PaymentRepository::get(
            paymentId: Metadata::getPaymentId(order: $order)
        );

        $status = '';

        if ($payment->isCaptured()) {
            $status = 'completed';
        } elseif ($payment->isCancelled()) {
            $status = 'cancelled';
        } elseif ($payment->isRefunded()) {
            $status = 'refunded';
        }

        CallbackModule::updateOrderStatus(order: $order, status: $status);
    }
}
