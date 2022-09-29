<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities\DataConverter\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * To test stdClass class conversion of objects specifying arrays.
 */
class ArrayDummy extends Model
{
    /**
     * @param int $int
     * @param array $arr
     */
    public function __construct(
        public int $int,
        public array $arr
    ) {
    }
}
