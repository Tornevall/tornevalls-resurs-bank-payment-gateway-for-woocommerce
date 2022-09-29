<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Customer\DeviceInfo;

/**
 * Customer address data from a payment.
 */
class Customer extends Model
{
    /**
     * @param DeliveryAddress|null $deliveryAddress
     * @param CustomerType|null $customerType
     * @param string|null $contactPerson
     * @param string $email
     * @param string|null $governmentId
     * @param string|null $mobilePhone
     * @param DeviceInfo|null $deviceInfo
     */
    public function __construct(
        public readonly ?DeliveryAddress $deliveryAddress,
        public readonly ?CustomerType $customerType,
        public readonly ?string $contactPerson,
        /**
         * @todo Don't know how to validate email.
         */
        public readonly string $email,
        /**
         * @todo Don't know how to validate governmentId.
         */
        public readonly ?string $governmentId,
        /**
         * @todo Don't know how to validate phone number.
         */
        public readonly ?string $mobilePhone,
        public readonly ?DeviceInfo $deviceInfo,
    ) {
    }
}
