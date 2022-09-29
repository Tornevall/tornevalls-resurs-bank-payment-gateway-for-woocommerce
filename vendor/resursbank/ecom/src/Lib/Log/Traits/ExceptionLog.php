<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Log\Traits;

use Exception;
use Resursbank\Ecom\Config;
use function get_class;
use function is_object;

/**
 * Write Exception to log file.
 */
trait ExceptionLog
{
    /**
     * @param Exception $exception
     * @return void
     */
    public static function logException(
        Exception $exception
    ): void {
        Config::$instance->logger->debug(message: '--------------------------');
        Config::$instance->logger->debug(message: '[EXCEPTION]');
        Config::$instance->logger->debug(message: $exception);

        $orig = $exception->getPrevious();

        if ($orig instanceof Exception) {
            Config::$instance->logger->debug(message: '[ORIGINAL EXCEPTION]');
            Config::$instance->logger->debug(message: $orig);
        }

        Config::$instance->logger->debug(message: '--------------------------');
    }
}
