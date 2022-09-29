<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Model\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Class with property which is an array for testing
 */
class ArrayPropertyDummy extends Model
{
    public function __construct(
        public array $array,
        public string $message
    ) {
    }
}
