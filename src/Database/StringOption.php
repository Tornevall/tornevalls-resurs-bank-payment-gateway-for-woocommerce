<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

use function is_string;

/**
 * Handle string values in database.
 */
class StringOption extends Option
{
    public static function getData(): string
    {
        $result = parent::getData();

        return is_string(value: $result) ? $result : '';
    }

    public static function getDefault(): string
    {
        return '';
    }
}
