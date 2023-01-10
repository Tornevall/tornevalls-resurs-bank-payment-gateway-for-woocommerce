<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Woocommerce\Settings;
use RuntimeException;
use WC_Order;
use WC_Order_Refund;
use wpdb;

use function is_string;

/**
 * Actions to handle raw database requests.
 *
 * @psalm-suppress MissingDependency
 */
class Database
{
    /**
     * Get the order ID from the database, based on the metadata stored on it for which Resurs always sets
     * the uuid as order reference. This is the only proper way to fetch such data.
     *
     * @noinspection SqlResolve
     * @todo Refactor then remove phpcs:ignore comment below. WOO-893
     */
    // phpcs:ignore
    public static function getOrderByPaymentId(string $paymentId): WC_Order
    {
        global $wpdb;

        if ($wpdb instanceof wpdb) {
            $tableName = $wpdb->prefix . 'postmeta';
            // Using WP safe queries to fetch data from the metas. This is the only way doing it properly
            // as WP do not provide any other way to fetch data from the database.
            /** @var string $orderResult */
            $orderResult = $wpdb->get_var(
                query: $wpdb->prepare(
                    "SELECT `post_id` FROM {$tableName} WHERE `meta_key` = '%s' and `meta_value` = '%s'",
                    Settings::PREFIX . '_payment_id',
                    $paymentId
                )
            );

            if (is_string(value: $orderResult) && $orderResult !== '') {
                /** @var bool|WC_Order|WC_Order_Refund $order */
                $order = wc_get_order(the_order: $orderResult);

                if ($order instanceof WC_Order) {
                    return $order;
                }
            }
        }

        throw new RuntimeException(message: 'No such order.');
    }
}
