<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment\Metadata;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Single Metadata custom Entry
 */
class Entry extends Model
{
    /**
     * @param string $key
     * @param string $value
     */
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
