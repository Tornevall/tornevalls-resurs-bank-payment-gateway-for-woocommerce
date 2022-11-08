<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Models\RegisterCallback;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines data for a digest.
 */
class DigestConfiguration extends Model
{
    /**
     * @param string $digestAlgorithm
     * @param string $digestSalt
     * @param array $digestParameters
     */
    public function __construct(
        public string $digestAlgorithm,
        public string $digestSalt,
        public array $digestParameters
    ) {
    }
}
