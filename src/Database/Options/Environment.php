<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Database interface for environment in wp_options table.
 *
 * @todo Add value validation against Enum inside Ecom. See WOO-799 & ECP-203
 */
class Environment extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'environment';
    }

    /**
     * Return default value.
     */
    public static function getDefault(): string
    {
        return 'test';
    }
}
