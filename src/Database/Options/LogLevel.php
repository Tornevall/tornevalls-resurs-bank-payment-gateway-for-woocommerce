<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Woocommerce\Database\StringOption;
use ValueError;

/**
 * Setting for globally enabling the gateway (not the plugin).
 */
class LogLevel extends IntOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'loglevel';
    }

    /**
     * Fetch configured log level as an actual LogLevel case
     */
    public static function getLogLevel(): EcomLogLevel
    {
        $value = get_option(
            option: static::getName(),
            default: EcomLogLevel::INFO->value
        );

        try {
            return EcomLogLevel::from((int)$value);
        } catch (ValueError) {
            return EcomLogLevel::INFO;
        }
    }

    public static function getDefault(): string
    {
        return EcomLogLevel::INFO->value;
    }
}
