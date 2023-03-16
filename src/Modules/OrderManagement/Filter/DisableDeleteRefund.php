<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

use Automattic\WooCommerce\Admin\PageController;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;

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

        if (
            !is_numeric(value: $orderId) ||
            (new PageController())->get_current_screen_id() !== 'shop_order'
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
