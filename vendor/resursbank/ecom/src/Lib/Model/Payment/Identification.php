<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Module\Payment\Enum\IdentificationType;

/**
 * Information about the identification made on a payment.
 */
class Identification extends Model
{
    /**
     * @param IdentificationType $type
     * @param string $reference
     */
    public function __construct(
        public readonly IdentificationType $type,
        public readonly string $reference = '',
    ) {
    }
}
