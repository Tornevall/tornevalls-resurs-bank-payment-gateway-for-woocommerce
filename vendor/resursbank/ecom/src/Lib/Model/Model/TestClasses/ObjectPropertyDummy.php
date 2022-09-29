<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Model\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Class with property which is an object for testing
 */
class ObjectPropertyDummy extends Model
{
    public function __construct(
        public SimpleDummy $object,
        public string $message
    ) {
    }
}
