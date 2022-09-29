<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Validation;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\MissingKeyException;

use function is_float;
use function is_int;
use function strlen;

/**
 * Methods to validate floats.
 */
class FloatValidation
{
    /**
     * Validates the supplied array contains an element named $key and that
     * element contains a float. Returns the validated float.
     *
     * @param array $data
     * @param string $key
     * @param bool $parseInt
     * @return float
     * @throws IllegalTypeException
     * @throws MissingKeyException
     */
    public function getKey(
        array $data,
        string $key,
        bool $parseInt = false
    ): float {
        if (!isset($data[$key])) {
            throw new MissingKeyException(
                message: "Missing $key key in array."
            );
        }

        if (is_int(value : $data[$key]) && $parseInt) {
            $data[$key] = (float) $data[$key];
        }

        if (!is_float(value: $data[$key])) {
            throw new IllegalTypeException(
                message: "$key is not a float."
            );
        }

        return $data[$key];
    }

    /**
     * @param float $value
     * @return bool
     * @throws IllegalValueException
     */
    public function isPositive(
        float $value
    ): bool {
        if ($value < 0) {
            throw new IllegalValueException(
                message: "$value may not be negative."
            );
        }

        return true;
    }

    /**
     * Validates that a float value is within the given min and max range.
     *
     * @param float $value
     * @param float $min
     * @param float $max
     * @return bool
     * @throws IllegalValueException
     */
    public function inRange(float $value, float $min, float $max): bool
    {
        if ($max < $min) {
            throw new IllegalValueException(
                message: 'Argument $max ' . "($max) " . 'is less than $min' .
                "($min)."
            );
        }

        if ($value < $min || $value > $max) {
            throw new IllegalValueException(
                message: "$value is not in range. $value needs to be within " .
                ">=$min & <=$max."
            );
        }

        return true;
    }

    /**
     * Validates that a float value has a number of decimals within the given
     * min and max range.
     *
     * @param float $value
     * @param int $min
     * @param int $max
     * @return bool
     * @throws IllegalValueException
     */
    public function length(float $value, int $min, int $max): bool
    {
        $len = strlen(string: (string) $this->getFraction(num: $value));

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
                message: "Float \"$value\" has invalid number of decimals. " .
                "Decimals counted to $len. Allowed range is from $min to $max."
            );
        }

        return true;
    }

    /**
     * Returns the decimal portion of a float value as an integer.
     * Example: getFraction(1.234) => 234
     *
     * @param float $num
     * @return int
     */
    private function getFraction(float $num): int
    {
        $strNum = strstr(haystack: (string) $num, needle: '.');
        return (int) ($strNum ? substr(string: $strNum, offset: 1) : '');
    }
}
