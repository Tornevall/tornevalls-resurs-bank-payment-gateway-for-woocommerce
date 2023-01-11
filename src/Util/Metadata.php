<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use ResursBank\Gateway\ResursDefault;
use WC_Order;

/**
 * Order metadata handler.
 */
class Metadata
{
    /**
     * Set meta data to WC Order
     */
    public static function setOrderMeta(
        WC_Order $order,
        string $metaDataKey,
        string $metaDataValue
    ): bool {
        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: ResursDefault::PREFIX . '_' . $metaDataKey,
            meta_value: $metaDataValue,
            unique: true
        );
    }

    public static function getOrderMeta(
        WC_Order $order,
        string $metaDataKey
    ): string {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: ResursDefault::PREFIX . '_' . $metaDataKey,
            single: true
        );
    }

    /**
     * Check if current order is a valid Resurs Payment.
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        return self::getOrderMeta(
            order: $order,
            metaDataKey: 'payment_id'
        ) !== '';
    }
}
