<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Validation;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\MissingKeyException;

use function in_array;
use function is_string;
use function strlen;

/**
 * Methods to validate strings.
 */
class StringValidation
{
    /**
     * Validates the supplied array contains an element named $key and that
     * element contains a string. Returns the validated string.
     *
     * @param array $data
     * @param string $key
     * @return string
     * @throws MissingKeyException
     * @throws IllegalTypeException
     */
    public function getKey(array $data, string $key): string
    {
        if (!isset($data[$key])) {
            throw new MissingKeyException(
                message: "Missing $key key in array."
            );
        }

        if (!is_string(value: $data[$key])) {
            throw new IllegalTypeException(
                message: "$key is not a string."
            );
        }

        return $data[$key];
    }

    /**
     * Validates string is not empty.
     *
     * @param string $value
     * @return bool
     * @throws EmptyValueException
     */
    public function notEmpty(
        string $value
    ): bool {
        if (trim(string: $value) === '') {
            throw new EmptyValueException(message: 'String cannot be empty.');
        }

        return true;
    }

    /**
     * Validates string matches supplied regex.
     *
     * @param string $value
     * @param string $pattern
     * @return bool
     * @throws IllegalCharsetException
     */
    public function matchRegex(
        string $value,
        string $pattern
    ): bool {
        if (!preg_match(pattern: $pattern, subject: $value)) {
            throw new IllegalCharsetException(
                message: "$value does not match $pattern"
            );
        }

        return true;
    }

    /**
     * Validates $value exists within $set.
     *
     * @param string $value
     * @param array<string> $set
     * @return bool
     * @throws IllegalValueException
     */
    public function oneOf(
        string $value,
        array $set
    ): bool {
        if (!in_array(needle: $value, haystack: $set, strict: true)) {
            throw new IllegalValueException(
                message:
                    "$value is not one of " .
                    implode(separator: ',', array: $set)
            );
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalCharsetException
     */
    public function isInt(string $value): bool
    {
        if (preg_match(pattern: '/\D/', subject: $value)) {
            throw new IllegalCharsetException(
                message: "$value cannot be int cast."
            );
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isTimestampDate(string $value): bool
    {
        $time = strtotime(datetime: $value);

        if ($time === false) {
            throw new IllegalValueException(
                message: "$value could not be converted to a timestamp."
            );
        }

        return true;
    }

    /**
     * @param string $value
     * @param int $min
     * @param int $max
     * @return bool
     * @throws IllegalValueException
     */
    public function length(string $value, int $min, int $max): bool
    {
        $len = strlen(string: $value);

        if ($max < $min) {
            throw new IllegalValueException(
                message: 'Argument $max ' . "($max) " . 'is less than $min' .
                "($min)."
            );
        }

        if ($min < 0) {
            throw new IllegalValueException(
                message: 'Argument $min may not be a negative integer.'
            );
        }

        if ($len < $min || $len > $max) {
            throw new IllegalValueException(
                message: "String \"$value\" has invalid length. " .
                "Length is $len. Allowed range is from $min to $max."
            );
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isUuid(string $value): bool
    {
        if (
            !preg_match(
                pattern: '/^[\da-f]{8}-[\da-f]{4}-[0-5][\da-f]{3}-[\da-d][\da-f]{3}-[\da-f]{12}$/i',
                subject: $value
            )
        ) {
            throw new IllegalValueException(message: "$value is not a UUID.");
        }

        return true;
    }

    /**
     * Performs basic email address validation
     *
     * @param string|null $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isEmail(?string $value): bool
    {
        if (!empty($value) && !str_contains(haystack: $value, needle: '@')) {
            throw new IllegalValueException(message: $value . ' is not an email address.');
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isSwedishSsn(
        string $value
    ): bool {
        if (
            !preg_match(
                pattern: '/^(18\d{2}|19\d{2}|20\d{2}|\d{2})' .
                '(0[1-9]|1[0-2])' .
                '(0[1-9]|[1-2][0-9]|3[0-1])' .
                '([-+])?(\d{4})$/',
                subject: $value
            )
        ) {
            throw new IllegalValueException(
                message: "$value is not a properly formatted Swedish SSN."
            );
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isSwedishOrg(
        string $value
    ): bool {
        if (
            !preg_match(
                pattern: '/^(16\d{2}|18\d{2}|19\d{2}|20\d{2}|\d{2})' .
                '(\d{2})(\d{2})([-+])?(\d{4})$/',
                subject: $value
            )
        ) {
            throw new IllegalValueException(
                message: "$value is not a properly formatted Swedish org. nr."
            );
        }

        return true;
    }
}
