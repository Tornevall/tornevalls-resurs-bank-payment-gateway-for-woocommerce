<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines order line (product) collection.
 */
class OrderLineCollection extends Collection
{
    /**
     * @param array<int, OrderLine> $data
     * @throws IllegalTypeException
     */
    public function __construct(array $data)
    {
        parent::__construct(data: $data, type: OrderLine::class);
    }
}
