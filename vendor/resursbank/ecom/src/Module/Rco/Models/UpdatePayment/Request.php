<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\UpdatePayment;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Module\Rco\Models\OrderLineCollection;

/**
 * Defines an UpdatePayment request object.
 */
class Request extends Model
{
    /**
     * @param OrderLineCollection $orderLines
     */
    public function __construct(
        public OrderLineCollection $orderLines
    ) {
    }
}
