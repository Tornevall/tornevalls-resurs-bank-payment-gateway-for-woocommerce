<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Payment method pagination.
 */
class Pagination extends Model
{
    /**
     * @param int $number - 32-bit integer.
     * @param int $size - 32-bit integer.
     * @param int $totalElements - 64-bit integer.
     * @param int $totalPages - 32-bit integer.
     */
    public function __construct(
        public readonly int $number,
        public readonly int $size,
        public readonly int $totalElements,
        public readonly int $totalPages,
    ) {
    }
}
