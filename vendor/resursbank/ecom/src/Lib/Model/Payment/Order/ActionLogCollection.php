<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment\Order;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines order line (product) collection.
 */
class ActionLogCollection extends Collection
{
    /**
     * @param array<int, ActionLog> $data
     * @throws IllegalTypeException
     */
    public function __construct(array $data)
    {
        parent::__construct(data: $data, type: ActionLog::class);
    }
}
