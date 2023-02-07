<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;
use WC_Order;

/**
 * Parent class with shared methods for status change callback classes.
 */
class Status
{
    /**
     * Fetches WooCommerce order object.
     *
     * @throws IllegalTypeException
     * @throws Throwable
     * @throws ConfigException
     */
    protected static function getWooCommerceOrder(int $orderId): WC_Order
    {
        try {
            $order = wc_get_order(the_order: $orderId);

            if (!$order instanceof WC_Order) {
                throw new IllegalTypeException(
                    message: 'Returned object not of type WC_Order'
                );
            }

            return $order;
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(msg: $error->getMessage());
            throw $error;
        }
    }

    /**
     * Fetches Resurs payment object.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    protected static function getResursPayment(string $paymentId, WC_Order $order, string $oldStatus): Payment
    {
        try {
            return Repository::get(paymentId: $paymentId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            $order->update_status(new_status: $oldStatus);
            throw $error;
        }
    }
}
