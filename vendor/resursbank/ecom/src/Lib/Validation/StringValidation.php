<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Validation;

use DateTime;
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
    public function isDate(string $value): bool
    {
        /**
         * @psalm-suppress TooFewArguments
         * @psalm-suppress InvalidNamedArgument
         * @noinspection PhpNamedArgumentMightBeUnresolvedInspection
         */
        $date = DateTime::createFromFormat(format: 'Y-m-d', datetime: $value);

        if (!$date || $date->format(format: 'Y-m-d') !== $value) {
            throw new IllegalValueException(message: "$value is not a date.");
        }

        return true;
    }

    /**
     * @param string $value
     * @return bool
     * @throws IllegalValueException
     * @psalm-suppress TooFewArguments
     * @psalm-suppress InvalidNamedArgument
     * @noinspection PhpNamedArgumentMightBeUnresolvedInspection
     */
    public function isIso8601Date(string $value): bool
    {
        $date = DateTime::createFromFormat(format: '', datetime: $value);
        if (!$date || $date->format(format: '') !== $value) {
            // Ugly hack because PHP's ISO 8601 parsing has been broken since forever and is still "not a bug"
            $noNanoseconds = substr(string: $value, offset: 0, length: 23);
            $remainder = substr(string: $value, offset: 23);
            if (
                !DateTime::createFromFormat(format: 'Y-m-d\TH:i:s.v', datetime: $noNanoseconds)
                || (!empty($remainder) && !ctype_digit(text: $remainder))
            ) {
                throw new IllegalValueException(message: "$value is not a valid ISO 8601 date.");
            }
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
        $len = strlen($value);

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
}
