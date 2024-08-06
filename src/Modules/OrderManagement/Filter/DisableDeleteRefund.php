<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Metadata;
use WC_Order;

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
        $orderId = $_GET['post'] ?? null;

        // Prioritize HPOS for order id.
        $testOrder = wc_get_order();

        if ($testOrder instanceof WC_Order) {
            $orderId = $testOrder->get_id();
        }

        if (
            !is_numeric(value: $orderId) ||
            !Admin::isInShopOrder()
        ) {
            return;
        }

        $order = OrderManagement::getOrder(id: (int) $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        echo <<<EOL
  <style>
    .refund .delete_refund {
      display: none !important;
    }  
  </style>
EOL;
    }
}
