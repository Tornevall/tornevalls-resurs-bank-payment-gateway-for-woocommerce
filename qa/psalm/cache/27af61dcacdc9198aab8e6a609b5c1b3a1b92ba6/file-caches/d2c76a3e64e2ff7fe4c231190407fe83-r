<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Log\Traits;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;

/**
 * Write Exception to log file.
 */
trait ExceptionLog
{
    /**
     * @param Exception $exception
     * @return void
     * @throws ConfigException
     * @todo Check if ConfigException validation needs a test.
     */
    public static function logException(
        Exception $exception
    ): void {
        Config::getLogger()->debug(message: '--------------------------');
        Config::getLogger()->debug(message: '[EXCEPTION]');
        Config::getLogger()->debug(message: $exception);

        $orig = $exception->getPrevious();

        if ($orig instanceof Exception) {
            Config::getLogger()->debug(message: '[ORIGINAL EXCEPTION]');
            Config::getLogger()->debug(message: $orig);
        }

        Config::getLogger()->debug(message: '--------------------------');
    }
}
