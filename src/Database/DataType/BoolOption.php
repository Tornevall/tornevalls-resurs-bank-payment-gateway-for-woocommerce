<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\DataType;

use Resursbank\Woocommerce\Database\Option;

/**
 * Resolve value from options table and typecast to bool.
 */
abstract class BoolOption extends Option
{
    /**
     * Booleans are stored in options table as 'yes' / 'no'.
     */
    public static function isEnabled(): bool
    {
        return (self::getRawData() ?? static::getDefault()) === 'yes';
    }

    /**
     * @return string|null To be compliant with OptionInterface contact.
     */
    public static function getDefault(): ?string
    {
        return 'no';
    }

    /**
     * Fetches stored option data as an actual boolean.
     */
    public static function getData(): bool
    {
        return self::getRawData() === 'yes';
    }
}
