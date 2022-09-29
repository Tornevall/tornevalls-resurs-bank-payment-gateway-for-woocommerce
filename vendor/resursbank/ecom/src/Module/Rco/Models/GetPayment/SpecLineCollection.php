<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\GetPayment;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines a collection of payment items.
 */
class SpecLineCollection extends Collection
{
    /**
     * @param array $data
     * @throws IllegalTypeException
     */
    public function __construct(array $data)
    {
        parent::__construct(
            data: $data,
            type: SpecLine::class
        );
    }
}
