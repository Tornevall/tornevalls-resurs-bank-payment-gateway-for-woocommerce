<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Validation\StringValidation;

use function is_string;

/**
 * Address information block about a payment.
 */
class Address extends Model
{
    /**
     * @param string $addressRow1
     * @param string $postalArea
     * @param string $postalCode
     * @param CountryCode|null $countryCode
     * @param string|null $fullName
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $addressRow2
     * @param StringValidation $stringValidation
     * @throws IllegalCharsetException
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly string $addressRow1,
        public readonly string $postalArea,
        public readonly string $postalCode,
        public readonly ?CountryCode $countryCode = null,
        public readonly ?string $fullName = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $addressRow2 = null,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateFullName();
        $this->validateFirstName();
        $this->validateLastName();
        $this->validateAddressRow1();
        $this->validateAddressRow2();
        $this->validatePostalArea();
        $this->validatePostalCode();
    }

    /**
     * @throws IllegalValueException
     */
    private function validateFullName(): void
    {
        if (is_string(value: $this->fullName)) {
            $this->stringValidation->length(
                value: $this->fullName,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function validateFirstName(): void
    {
        if (is_string(value: $this->firstName)) {
            $this->stringValidation->length(
                value: $this->firstName,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function validateLastName(): void
    {
        if (is_string(value: $this->lastName)) {
            $this->stringValidation->length(
                value: $this->lastName,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function validateAddressRow1(): void
    {
        $this->stringValidation->length(
            value: $this->addressRow1,
            min: 1,
            max: 100
        );
    }

    /**
     * @throws IllegalValueException
     */
    private function validateAddressRow2(): void
    {
        if (is_string(value: $this->addressRow2)) {
            $this->stringValidation->length(
                value: $this->addressRow2,
                min: 0,
                max: 100
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function validatePostalArea(): void
    {
        $this->stringValidation->length(
            value: $this->postalArea,
            min: 1,
            max: 50
        );
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     */
    private function validatePostalCode(): void
    {
        $this->stringValidation->length(
            value: $this->postalCode,
            min: 1,
            max: 10
        );

        $this->stringValidation->matchRegex(
            value: $this->postalCode,
            pattern: '/[ \d]+/'
        );
    }
}
