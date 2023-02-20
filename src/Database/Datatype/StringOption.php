<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Datatype;

use Resursbank\Woocommerce\Database\Option;

use function is_string;

/**
 * Handle string values in database.
 */
abstract class StringOption extends Option
{
    /**
     * Get data.
     */
    public static function getData(): string
    {
        $result = parent::getRawData();

        return is_string(value: $result) ? $result : '';
    }

    /**
     * @return string|null To be compliant with OptionInterface contact.
     */
    public static function getDefault(): ?string
    {
        return '';
    }
}
