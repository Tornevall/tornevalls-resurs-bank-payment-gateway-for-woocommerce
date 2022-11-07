<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Exception;

use Exception;
use Throwable;

/**
 * Exceptions thrown from CURL requests.
 */
class CurlException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param string|bool $body
     * @param int $httpCode
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        int $code,
        public readonly string|bool $body,
        public readonly int $httpCode = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous
        );
    }
}
