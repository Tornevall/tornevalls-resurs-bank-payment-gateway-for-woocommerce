<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Filter;

/**
 * Disable internal note to manually return funds after order is fully refunded.
 */
class DisableRefundNote
{
    /**
     * Event listener.
     */
    public static function exec(array $data): array
    {
        $note = __(
            text: 'Order status set to refunded. To return funds to the customer ' .
                'you will need to issue a refund through your payment gateway.',
            domain: 'woocommerce'
        );

        if (
            isset($data['comment_content']) &&
            $data['comment_content'] === $note
        ) {
            return [];
        }

        return $data;
    }
}
