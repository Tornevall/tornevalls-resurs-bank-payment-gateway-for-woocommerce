<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use ResursBank\Gateway\ResursDefault;
use RuntimeException;
use WC_Order;
use wpdb;

/**
 * Actions to handle raw database requests.
 */
class Database
{
    /**
     * @param string $orderReference
     * @return WC_Order
     * @since 0.0.1.0
     * @noinspection SqlResolve
     * @noinspection UnknownInspectionInspection
     */
    public static function getOrderByReference(string $orderReference): WC_Order
    {
        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $tableName = $wpdb->prefix . 'postmeta';
            // This is the WP safe way to do queries.
            $orderResult = $wpdb->get_var(
                query: $wpdb->prepare(
                    "SELECT `post_id` FROM {$tableName} WHERE `meta_key` = '%s' and `meta_value` = '%s'",
                    ResursDefault::PREFIX . '_order_reference',
                    $orderReference
                )
            );
            if (is_string($orderResult) && $orderResult !== '') {
                $order = wc_get_order($orderResult);
                if ($order instanceof WC_Order) {
                    return $order;
                }
            }
        }

        throw new RuntimeException('Could not get order by reference.');
    }
}