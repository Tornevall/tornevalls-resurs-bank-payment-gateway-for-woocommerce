<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\UpdatePaymentReference;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines an UpdatePaymentReference request object.
 */
class Request extends Model
{
    /**
     * @param string $paymentReference
     */
    public function __construct(
        public string $paymentReference
    ) {
    }
}
