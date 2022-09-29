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
class Callback extends Model
{
    /**
     * @param string|null $url
     * @param string|null $description
     */
    public function __construct(
        /**
         * @todo Don't know how to validate urls.
         */
        public readonly ?string $url,
        public readonly ?string $description,
    ) {
    }
}
