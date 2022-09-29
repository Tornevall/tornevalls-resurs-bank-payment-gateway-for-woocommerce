<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities\DataConverter\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * To test stdClass conversions.
 */
class SimpleDummy extends Model
{
    /**
     * @param int $int
     * @param string $message
     */
    public function __construct(
        public int $int,
        public string $message
    ) {
    }
}
