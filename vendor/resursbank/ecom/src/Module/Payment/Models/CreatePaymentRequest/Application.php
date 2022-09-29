<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
use Resursbank\Ecom\Lib\Validation\IntValidation;

/**
 * Application data for a payment.
 */
class Application extends Model
{
    /**
     * @param int|null $requestedCreditLimit
     * @param array|null $applicationData
     * @param IntValidation $intValidation
     * @param ArrayValidation $arrayValidation
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly ?int $requestedCreditLimit,
        public readonly ?array $applicationData,
        private readonly IntValidation $intValidation = new IntValidation(),
        private readonly ArrayValidation $arrayValidation = new ArrayValidation()
    ) {
        $this->validateRequestedCreditLimit();
        $this->validateApplicationData();
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateRequestedCreditLimit(): void
    {
        if ($this->requestedCreditLimit !== null) {
            $this->intValidation->inRange(
                value: $this->requestedCreditLimit,
                min: 1,
                max: 9999999999
            );
        }
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateApplicationData(): void
    {
        if ($this->applicationData !== null) {
            $this->arrayValidation->isAssoc(data: $this->applicationData);
        }
    }
}
