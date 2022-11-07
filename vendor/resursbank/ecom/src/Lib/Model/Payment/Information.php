<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Information and details about a payment.
 */
class Information extends Model
{
    /**
     * @param string $creator
     */
    public function __construct(
        public readonly string $creator,
    ) {
    }
}
