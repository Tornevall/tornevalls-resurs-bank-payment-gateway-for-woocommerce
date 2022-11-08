<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use BackedEnum;
use Resursbank\Ecom\Lib\Collection\Collection;

use function is_object;
use function is_array;

/**
 * Defines the basic structure of an Ecom model.
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class Model
{
    /**
     * Converts the object to an array suitable for use with the Curl library.
     *
     * @param bool $full
     * @param array $raw
     * @return array
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function toArray(
        bool $full = false,
        array $raw = []
    ): array {
        $data = [];

        $raw = $raw ?: get_object_vars(object: $this);

        foreach ($raw as $name => $value) {
            if (is_object(value: $value)) {
                // Skip DI.
                if ($value instanceof Collection || $value instanceof self) {
                    $data[$name] = $value->toArray(full: $full);
                }

                if ($value instanceof BackedEnum) {
                    $data[$name] = $value->value;
                }
            } elseif (is_array(value: $value)) {
                // Support arrays containing Model|Collection.
                $data[$name] = $this->toArray(full: $full, raw: $value);
            } else {
                $data[$name] = $value;
            }
        }

        return $data;
    }
}
