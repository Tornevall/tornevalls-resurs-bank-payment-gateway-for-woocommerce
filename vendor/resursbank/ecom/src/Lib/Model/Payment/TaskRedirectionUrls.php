<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Customer address data from a payment.
 */
class TaskRedirectionUrls extends Model
{
    /**
     * @param string $merchantUrl
     * @param string $customerUrl
     * @param string|null $coApplicantUrl
     */
    public function __construct(
        public string $merchantUrl,
        public string $customerUrl,
        public ?string $coApplicantUrl = null
    ) {
    }
}
