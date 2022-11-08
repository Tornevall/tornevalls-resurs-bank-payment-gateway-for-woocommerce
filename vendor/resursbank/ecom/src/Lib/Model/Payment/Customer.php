<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;

use function is_string;

/**
 * Customer data supplied to create a payment.
 */
class Customer extends Model
{
    /**
     * @param Address|null $deliveryAddress
     * @param CustomerType|null $customerType
     * @param string|null $contactPerson
     * @param string|null $email
     * @param string|null $governmentId
     * @param string|null $mobilePhone
     * @param DeviceInfo|null $deviceInfo
     * @param StringValidation $stringValidation
     * @throws IllegalValueException
     * @todo There are no validation rules declared for anything. Like phone, email, government id etc.
     * @todo NOTE: This should technically be CustomerRequest, and there should be a customerResponse, see ECP-252
     * @todo       Reason to avoid this is that governmentId validation will fail in CreatePayment CustomerResponse,
     * @todo       Since it seems wrong in the API documentation we do not know what to do right now.
     */
    public function __construct(
        public readonly ?Address $deliveryAddress = null,
        public readonly ?CustomerType $customerType = null,
        public readonly ?string $contactPerson = null,
        public readonly ?string $email = null,
        public readonly ?string $governmentId = null,
        public readonly ?string $mobilePhone = null,
        public readonly ?DeviceInfo $deviceInfo = null,
        protected readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validate();
    }

    /**
     * Validate object properties.
     *
     * NOTE: protected to allow override in CustomerResponse.
     *
     * @return void
     * @throws IllegalValueException
     */
    protected function validate(): void
    {
        $this->validateEmail();
    }

    /**
     * @throws IllegalValueException
     */
    private function validateEmail(): void
    {
        if (is_string(value: $this->email)) {
            $this->stringValidation->isEmail(value: $this->email);
        }
    }
}
