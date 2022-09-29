<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Data\Models\Address;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Customer address data from a payment.
 */
class Customer extends Model
{
    /**
     * @param CustomerType $customerType
     * @param string|null $email
     * @param string|null $governmentId
     * @param string|null $phone
     * @param string|null $mobilePhone
     * @param string|null $contactPerson
     * @param Address|null $deliveryAddress Delivery address can be unset in some occasions.
     * @param Identification|null $identification
     * @param StringValidation $stringValidation
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly CustomerType $customerType = CustomerType::NATURAL,
        public readonly ?string $email = null,
        /**
         * @todo Missing validation rules for gov id.
         */
        public readonly ?string $governmentId = null,
        /**
         * @todo Missing validation rules phone number.
         */
        public readonly ?string $phone = null,
        /**
         * @todo Missing validation rules phone number.
         */
        public readonly ?string $mobilePhone = null,
        public readonly ?string $contactPerson = null,
        public readonly ?Address $deliveryAddress = null,
        public readonly ?Identification $identification = null,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateEmail();
    }

    /**
     * @throws IllegalValueException
     */
    private function validateEmail(): void
    {
        $this->stringValidation->isEmail(value: $this->email);
    }
}
