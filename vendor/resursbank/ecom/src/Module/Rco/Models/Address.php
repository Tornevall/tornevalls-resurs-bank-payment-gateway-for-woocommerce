<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines customer address information.
 */
class Address extends Model
{
    /**
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $addressRow1
     * @param string|null $addressRow2
     * @param string|null $postalArea
     * @param string|null $postalCode
     * @param string|null $countryCode
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $addressRow1 = null,
        public ?string $addressRow2 = null,
        public ?string $postalArea = null,
        public ?string $postalCode = null,
        public ?string $countryCode = null
    ) {
    }
}
