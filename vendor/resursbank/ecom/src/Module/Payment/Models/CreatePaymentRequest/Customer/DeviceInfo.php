<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Customer;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Information and details about a payment.
 */
class DeviceInfo extends Model
{
    /**
     * @param string|null $ip
     * @param string|null $userAgent
     * @param StringValidation $stringValidation
     * @throws IllegalValueException
     */
    public function __construct(
        /**
         * @todo Don't know how to validate ip-address.
         */
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateUserAgent();
    }

    /**
     * @throws IllegalValueException
     */
    private function validateUserAgent(): void
    {
        if ($this->userAgent !== null) {
            $this->stringValidation->length(value: $this->userAgent, min: 1, max: 200);
        }
    }
}
