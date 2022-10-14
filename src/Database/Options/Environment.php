<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

namespace Resursbank\Woocommerce\Database\Options;

use function get_option;
use function update_option;

/**
 * Database interface for environment in wp_options table.
 */
class Environment
{
    /**
     * Name of the database table field this class touches in the wp_options
     * table.
     */
    public const NAME = Option::NAME_PREFIX . 'environment';

    /**
     * @return string|null
     */
    public static function getData(): ?string
    {
        return get_option(option: self::NAME, default: null);
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function setData(string $value): bool
    {
        return update_option(option: self::NAME, value: $value);
    }
}
