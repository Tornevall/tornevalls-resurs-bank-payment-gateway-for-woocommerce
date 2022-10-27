<?php /** @noinspection PhpCSValidationInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Log;

use Resursbank\Ecom\Config;

/**
 * Defines log levels used by loggers
 */
enum LogLevel: int
{
    case DEBUG = 0;
    case INFO = 1;
    case WARNING = 2;
    case ERROR = 3;
    case EXCEPTION = 4;

    /**
     * Checks if supplied log level should be logged according to current configured logLevel
     *
     * @param LogLevel $level
     * @return bool
     */
    public static function loggable(self $level): bool
    {
        if (!Config::hasInstance()) {
            return true; // If there's no Config instance there's no logLevel restriction to apply
        }

        return Config::$instance->logLevel->value <= $level->value;
    }
}
