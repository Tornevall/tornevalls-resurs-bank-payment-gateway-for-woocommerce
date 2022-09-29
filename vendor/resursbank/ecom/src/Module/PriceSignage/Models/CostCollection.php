<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PriceSignage\Models;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines cost entity collection.
 */
class CostCollection extends Collection
{
    /**
     * @param array $data
     * @throws IllegalTypeException
     */
    public function __construct(array $data)
    {
        parent::__construct(
            data: $data,
            type: Cost::class
        );
    }
}
