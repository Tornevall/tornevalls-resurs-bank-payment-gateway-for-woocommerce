<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines the basic structure of an Ecom model
 */
class Model
{
    /**
     * Converts the object to an array suitable for use with the Curl library
     *
     * @param mixed $item
     * @param bool $isRecursion
     * @return array
     */
    public function toArray(mixed $item = null, bool $isRecursion = false): array
    {
        if (!$item && !$isRecursion) {
            $item = $this;
        }

        $data = [];
        foreach ((array)$item as $name => $value) {
            if (is_object($value) || is_array($value)) {
                if ($value instanceof Collection) {
                    $data[$name] = $this->toArray(item: $value->toArray(), isRecursion: true);
                } else {
                    $data[$name] = $this->toArray(item: $value, isRecursion: true);
                }
            } else {
                $data[$name] = $value;
            }
        }

        return $data;
    }
}
