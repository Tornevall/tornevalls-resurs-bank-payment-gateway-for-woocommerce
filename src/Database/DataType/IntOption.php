<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\DataType;

use Resursbank\Woocommerce\Database\Option;

/**
 * Resolve value from options table and typecast to int.
 */
abstract class IntOption extends Option
{
    /**
     * Resolve data as integer.
     */
    public static function getData(): int
    {
        $result = parent::getRawData();
        $result = is_numeric(value: $result) ? $result : self::getDefault();

        return (int) $result;
    }

    /**
     * @return string|null To be compliant with OptionInterface contact (must be string).
     */
    public static function getDefault(): ?string
    {
        return '0';
    }
}
