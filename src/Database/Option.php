<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

use RuntimeException;

/**
 * Basic database interface for options in wp_options table.
 */
class Option
{
    /**
     * Name prefix for entries in wp_options table.
     */
    public const NAME_PREFIX = 'resursbank_';

    /**
     * Resolve name of entry in wp_options table. This method needs to be
     * overwritten by extending classes.
     *
     * NOTE: Using a method instead of a property to ensure that the name is
     * not left empty.
     *
     * @throws RuntimeException
     */
    public static function getName(): string
    {
        throw new RuntimeException(message: 'Not implemented');
    }

    /**
     * @return mixed - By default this should give string|null, defining mixed
     * to allow overriding classes to return other types.
     */
    public static function getData(): mixed
    {
        return get_option(option: static::getName(), default: null);
    }

    public static function setData(string $value): bool
    {
        return update_option(
            option: static::getName(),
            value: $value
        ) === true;
    }
}
