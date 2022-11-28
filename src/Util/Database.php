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
     * Get the order ID from the database, based on the metadata stored on it for which Resurs always sets
     * the uuid as order reference. This is the only proper way to fetch such data.
     * @param string $orderReference
     * @return WC_Order
     * @noinspection SqlResolve
     * @noinspection UnknownInspectionInspection
     */
    public static function getOrderByReference(string $orderReference): WC_Order
    {
        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $tableName = $wpdb->prefix . 'postmeta';
            // Using WP safe queries to fetch data from the metas. This is the only way doing it properly
            // as WP do not provide any other way to fetch data from the database.
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

        throw new RuntimeException('No such order.');
    }
}