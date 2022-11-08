<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Data\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Address information block about a payment.
 */
class Address extends Model
{
    /**
     * @param string $fullName
     * @param string $addressRow1
     * @param string $postalArea
     * @param string $postalCode
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $addressRow2
     * @param string|null $countryCode
     */
    public function __construct(
        public readonly string $fullName,
        public readonly string $addressRow1,
        public readonly string $postalArea,
        public readonly string $postalCode,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $addressRow2 = null,
        public readonly ?string $countryCode = null,
    ) {
    }
}
