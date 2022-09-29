<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Log;

use Error;
use Exception;

/**
 * Contract for a logger implementation.
 */
interface LoggerInterface
{
    /**
     * @param string|Exception|Error $message
     * @return void
     */
    public function debug(string|Exception|Error $message): void;

    /**
     * @param string|Exception $message
     * @return void
     */
    public function info(string|Exception $message): void;

    /**
     * @param string|Exception $message
     * @return void
     */
    public function warning(string|Exception $message): void;

    /**
     * @param string|Exception $message
     * @return void
     */
    public function error(string|Exception $message): void;
}
