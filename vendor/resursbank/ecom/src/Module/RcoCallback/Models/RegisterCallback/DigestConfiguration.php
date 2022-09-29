<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Models\RegisterCallback;

use Resursbank\Ecom\Lib\Model\Model;

class DigestConfiguration extends Model
{
    public function __construct(
        public string $digestAlgorithm,
        public string $digestSalt,
        public array $digestParameters
    ) {
    }
}
