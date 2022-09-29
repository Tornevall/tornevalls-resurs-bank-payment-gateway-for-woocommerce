<?php

/** @noinspection MessDetectorValidationInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Validation;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\MissingKeyException;

use function is_bool;

/**
 * Methods to validate booleans.
 */
class BoolValidation
{
    /**
     * Validates the supplied array contains an element named $key and that
     * element contains a boolean value. Returns the validated boolean.
     *
     * @param array $data
     * @param string $key
     * @return bool
     * @throws MissingKeyException
     * @throws IllegalTypeException
     */
    public function getKey(array $data, string $key): bool
    {
        if (!isset($data[$key])) {
            throw new MissingKeyException(
                message: "Missing $key key in array."
            );
        }

        if (!is_bool(value: $data[$key])) {
            throw new IllegalTypeException(
                message: "$key is not a bool."
            );
        }

        return $data[$key];
    }
}
