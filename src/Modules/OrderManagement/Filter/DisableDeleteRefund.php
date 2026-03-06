<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\OrderManagement\Filter;

use Resursbankabpayments\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbankabpayments\Woocommerce\Util\Admin;
use Resursbankabpayments\Woocommerce\Util\Metadata;
use WC_Order;
use WP_Post;

/**
 * Disable control to delete applied refunds.
 */
class DisableDeleteRefund
{
    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function exec(): void
    {
        $orderId = 0;
        $post = get_post();

        if ($post instanceof WP_Post) {
            $orderId = $post->ID;
        }

        // Prioritize HPOS for order id.
        $testOrder = wc_get_order();

        if ($testOrder instanceof WC_Order) {
            $orderId = $testOrder->get_id();
        }

        if (
            $orderId <= 0 ||
            !Admin::isInShopOrderEdit()
        ) {
            return;
        }

        $order = OrderManagement::getOrder(id: (int) $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        echo '<style>' .
            '.refund .delete_refund {' .
            'display: none !important;' .
            '}' .
            '</style>';
    }
}
