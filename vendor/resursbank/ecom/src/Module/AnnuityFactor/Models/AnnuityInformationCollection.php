<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\AnnuityFactor\Models;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines annuity information collection.
 */
class AnnuityInformationCollection extends Collection
{
    /**
     * @param array<int, AnnuityInformation> $data
     * @throws IllegalTypeException
     */
    public function __construct(
        public readonly array $data
    ) {
        parent::__construct(data: $data, type: AnnuityInformation::class);
    }
}
