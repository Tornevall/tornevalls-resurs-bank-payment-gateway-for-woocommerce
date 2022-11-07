<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Network;

use Resursbank\Ecom\Lib\Model\Model;
use stdClass;

/**
 * Curl response.
 */
class Response extends Model
{
    /**
     * @param stdClass|array $body
     * @param int $code
     */
    public function __construct(
        public readonly stdClass|array $body,
        public readonly int $code
    ) {
    }
}
