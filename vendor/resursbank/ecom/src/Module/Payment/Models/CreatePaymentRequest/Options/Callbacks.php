<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Application data for a payment.
 */
class Callbacks extends Model
{
    /**
     * @param Callback|null $authorization
     * @param Callback|null $management
     */
    public function __construct(
        /**
         * @todo Don't know how to validate urls.
         */
        public readonly ?Callback $authorization,
        /**
         * @todo Don't know how to validate urls.
         */
        public readonly ?Callback $management,
    ) {
    }
}
