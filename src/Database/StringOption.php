<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

/**
 * Handle string values in database.
 */
class StringOption extends Option
{
    /**
     * @return string
     */
    public static function getData(): string
    {
        $result = parent::getData();

        return is_string($result) ? $result : '';
    }
}