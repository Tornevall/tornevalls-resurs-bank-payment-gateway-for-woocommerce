<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Model\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Simple use of the Model class for testing
 */
class SimpleDummy extends Model
{
    public function __construct(
        public int $number,
        public string $message
    ) {
    }
}
