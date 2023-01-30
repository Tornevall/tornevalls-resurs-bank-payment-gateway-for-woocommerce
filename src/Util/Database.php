<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use RuntimeException;
use WC_Order;
use wpdb;
use function is_string;

/**
 * Actions to handle raw database requests.
 */
class Database
{
    /**
     * Resolve WC_Order object for supplied payment id (the uuid of the payment
     * at Resurs Bank).
     *
     * @throws RuntimeException
     * @todo This method handles metadata, it should be part of the Metadata class. WOO-1014
     */
    public static function getOrderByPaymentId(string $paymentId): WC_Order
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            throw new RuntimeException(
                message: 'WordPress DB connection not defined.'
            );
        }

        $orderResult = $wpdb->get_var(
            query: $wpdb->prepare(
                "SELECT `post_id` FROM {$wpdb->prefix}postmeta WHERE `meta_key` = '%s' and `meta_value` = '%s'",
                RESURSBANK_MODULE_PREFIX . '_payment_id',
                $paymentId
            )
        );

        if (is_string(value: $orderResult) && $orderResult !== '') {
            $order = wc_get_order(the_order: $orderResult);

            if ($order instanceof WC_Order) {
                return $order;
            }
        }

        throw new RuntimeException(message: "No order matching $paymentId");
    }

    /**
     * The only way to get information about a specific product type.
     *
     * @param int $itemId
     * @return string
     */
    public static function getProductItemType(int $itemId): string
    {
        global $wpdb;
        $return = $wpdb->get_var(
            query: $wpdb->prepare(
                query: "SELECT order_item_type FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = '%d'",
                args: $itemId
            )
        );

        return is_string(value: $return) ? $return : '';
    }
}
