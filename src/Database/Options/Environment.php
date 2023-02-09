<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Woocommerce\Database\Option;

use function is_string;

/**
 * Database interface for environment in wp_options table.
 */
class Environment extends Option
{
    /**
     * @inheritdoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'environment';
    }

    /**
     * Return default value.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): string
    {
        return EnvironmentEnum::TEST->value;
    }

    /**
     * Get the data.
     */
    public static function getData(): EnvironmentEnum
    {
        $data = parent::getData();

        if (!is_string(value: $data)) {
            $data = self::getDefault();
        }

        return EnvironmentEnum::from(value: $data);
    }
}
