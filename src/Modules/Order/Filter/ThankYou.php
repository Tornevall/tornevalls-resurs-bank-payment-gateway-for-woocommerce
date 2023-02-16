<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

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
use Resursbank\Woocommerce\Modules\Order\Status;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Event executed when "Thank You" page is rendered after completing checkout.
 */
class ThankYou
{
    /**
     * Register event listener.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_thankyou',
            callback: static function ($order_id = null): void {
                try {
                    self::exec(orderId: $order_id);
                } catch (Throwable $e) {
                    Log::error(error: $e);
                }
            },
            priority: 10,
            accepted_args: 1
        );
    }

    /**
     * 1. Use order metadata to remember that "Thank You" page has rendered.
     * 2. Sync order status in WP with payment at Resurs Bank.
     *
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private static function exec(mixed $orderId = null): void
    {
        // @todo Not sure what happens here if $order_id is null, could we affect the wrong order?
        $order = new WC_Order(order: $orderId);

        if (!$order instanceof WC_Order) {
            throw new IllegalTypeException(
                message: "Failed to resolve order from id $orderId"
            );
        }

        if (Metadata::isThankYouTriggered(order: $order)) {
            return;
        }

        Status::update(order: $order);
        Metadata::setThankYouTriggered(order: $order);

        $order->add_order_note(
            note: Translator::translate(
                phraseId: 'customer-landingpage-return'
            )
        );
    }
}
