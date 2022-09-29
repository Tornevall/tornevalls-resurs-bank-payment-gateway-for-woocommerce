<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Locale;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;

use function in_array;

/**
 * Declares available countries for API accounts.
 */
class Country
{
    public const DK = 'dk';
    public const SE = 'se';
    public const FI = 'fi';
    public const NO = 'no';

    /**
     * @param string $country
     * @throws EmptyValueException
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $country
    ) {
        if ($this->country === '') {
            throw new EmptyValueException(message: 'Country');
        }

        if (!self::isAvailable(country: $this->country)) {
            throw new ValidationException(
                message: 'Supplied country is not available.'
            );
        }
    }

    /**
     * Check whether supplied $country is declared within this class.
     *
     * @param string $country
     * @return bool
     */
    public static function isAvailable(string $country): bool
    {
        return in_array(
            needle: $country,
            haystack: self::getList(),
            strict: true
        );
    }

    /**
     * @return array<int, string>
     */
    public static function getList(): array
    {
        return [
            self::DK,
            self::SE,
            self::FI,
            self::NO
        ];
    }
}
