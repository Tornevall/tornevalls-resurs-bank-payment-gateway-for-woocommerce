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
class ParticipantRedirectionUrls extends Model
{
    /**
     * @param string|null $failUrl
     * @param string|null $successUrl
     */
    public function __construct(
        /**
         * @todo Don't know how to validate urls.
         */
        public readonly ?string $failUrl,
        /**
         * @todo Don't know how to validate urls.
         */
        public readonly ?string $successUrl,
    ) {
    }
}
