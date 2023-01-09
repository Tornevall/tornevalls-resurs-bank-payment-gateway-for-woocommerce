<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Woocommerce\Settings;
use WC_Order;

/**
 * Order metadata handler.
 *
 * @psalm-suppress MissingDependency
 */
class Metadata
{
    /**
     * Set metadata to an order.
     * Metadata is stored uniquely (meaning the returned data from getOrderMeta can be returned as $single=true).
     */
    public static function setOrderMeta(
        WC_Order $order,
        string $metaDataKey,
        string $metaDataValue
    ): bool {
        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: self::getPrefix() . '_' . $metaDataKey,
            meta_value: $metaDataValue,
            unique: true
        );
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     */
    public static function getOrderMeta(WC_Order $order, string $metaDataKey): string
    {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: self::getPrefix() . '_' . $metaDataKey,
            single: true
        );
    }

    /**
     * Check if current order is a valid Resurs Payment.
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        return Metadata::getOrderMeta(
            order: $order,
            metaDataKey: 'payment_id'
        ) !== '';
    }

    /**
     * Reported fix: Left operand cannot be mixed (see https://psalm.dev/059)
     */
    private static function getPrefix(): string
    {
        return Settings::PREFIX;
    }
}
