<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

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
        $existingMeta = get_post_meta(
            post_id: $order->get_id(),
            key: RESURSBANK_MODULE_PREFIX . '_' . $metaDataKey,
            single: true
        );

        if ($existingMeta) {
            return (bool)update_post_meta(
                post_id: $order->get_id(),
                meta_key: RESURSBANK_MODULE_PREFIX . '_' . $metaDataKey,
                meta_value: $metaDataValue
            );
        }

        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: RESURSBANK_MODULE_PREFIX . '_' . $metaDataKey,
            meta_value: $metaDataValue,
            unique: true
        );
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     */
    public static function getOrderMeta(
        WC_Order $order,
        string $metaDataKey
    ): string {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: RESURSBANK_MODULE_PREFIX . '_' . $metaDataKey,
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
     * Fetch order information and metadata.
     *
     * @return array
     */
    public static function getOrderInfo(WC_Order $order): array
    {
        $meta = get_post_custom(post_id: $order->get_id());
        return [
            'order' => $order,
            'meta' => is_array(value: $meta) ? $meta : [],
        ];
    }
}
