<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities\DataConverter\TestClasses;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * To test stdClass conversion of objects specifying object properties.
 */
class ComplexDummy extends Model

{
    /**
     * @param int $int
     * @param SimpleDummy $simpleDummy
     * @param SimpleDummyCollection $simpleDummyCollection
     * @noinspection MessDetectorValidationInspection
     */
    public function __construct(
        public int $int,
        public SimpleDummy $simpleDummy,
        public SimpleDummyCollection $simpleDummyCollection
    ) {
    }
}
