<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

/**
 * Common functionality for actions.
 */
abstract class Action
{
    /**
     * Generate a transaction ID.
     */
    public static function generateTransactionId(): string
    {
        return time() . mt_rand();
    }
}
