<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

/**
 * Handle int values in database.
 */
class IntOption extends Option
{
    public static function getData(): int
    {
        $result = parent::getData();
        return is_numeric(value: $result) ? (int)$result : PHP_INT_MAX;
    }
}
