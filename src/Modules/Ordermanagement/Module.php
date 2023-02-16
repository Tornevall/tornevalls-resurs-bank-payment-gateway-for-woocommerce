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
use Throwable;

/**
 * Sets up actions for order status change hooks. Called from PluginHooks::getActions.
 */
class Module
{
    /**
     * The actual method that sets up actions for order status change hooks.
     */
    public static function setupActions(): void
    {
        add_action(
            hook_name: 'woocommerce_order_status_changed',
            callback: 'Resursbank\Woocommerce\Modules\Ordermanagement\Module::callback',
            priority: 10,
            accepted_args: 3
        );
        add_action(
            hook_name: 'woocommerce_order_refunded',
            callback: 'Resursbank\Woocommerce\Modules\Ordermanagement\Refunded::performRefund',
            priority: 10,
            accepted_args: 2
        );
    }

    /**
     * Match new order status to the correct method.
     * No inspection added on $old since filter is built on this as a second parameter.
     *
     * @throws ConfigException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws Throwable
     * @noinspection PhpUnusedParameterInspection
     */
    // phpcs:ignore
    public static function callback(int $orderId, string $old, string $new): void
    {
        match ($new) {
            'completed' => Completed::capture(orderId: $orderId),
            'cancelled' => Cancelled::cancel(orderId: $orderId),
            default => Config::getLogger()->debug(
                message: 'No matching status handler found'
            )
        };
    }
}
