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
 */
class Metadata
{
    /**
     * @param WC_Order $order
     * @param string $metaDataKey
     * @param string $metaDataValue
     * @return bool
     */
    public static function setOrderMeta(WC_Order $order, string $metaDataKey, string $metaDataValue): bool
    {
        return (bool)add_post_meta(
            $order->get_id(),
            $metaDataKey,
            $metaDataValue
        );
    }
}
