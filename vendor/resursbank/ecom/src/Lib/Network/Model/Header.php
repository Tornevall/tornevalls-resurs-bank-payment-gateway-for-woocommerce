<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network\Model;

/**
 * Defines basic request header.
 */
class Header
{
    /**
     * @param string $key
     * @param string|int $value
     */
    public function __construct(
        public readonly string $key,
        public readonly string|int $value
    ) {
    }
}
