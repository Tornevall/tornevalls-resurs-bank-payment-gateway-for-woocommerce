<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Repository\Traits;

use InvalidArgumentException;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Collection\Collection;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use stdClass;

use function is_array;
use function is_string;

/**
 * Convert anonymous data to model instance(s).
 */
trait ModelConverter
{
    /**
     * Convert JSON data to model instance(s).
     *
     * @param string|array|stdClass $data
     * @param class-string $model
     * @return Collection|Model
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public function convertToModel(
        string|array|stdClass $data,
        string $model
    ): Collection|Model {
        $result = null;

        $this->validateModel(model: $model);

        /** @psalm-suppress MixedAssignment */
        if (is_string(value: $data)) {
            $data = json_decode(
                json: $data,
                associative: false,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );
        }

        if (is_array(value: $data)) {
            /** @psalm-suppress MixedAssignment */
            $result = DataConverter::arrayToCollection(
                data: $data,
                targetType: $model
            );
        } elseif ($data instanceof stdClass) {
            /** @psalm-suppress MixedAssignment */
            $result = DataConverter::stdClassToType(
                object: $data,
                type: $model
            );
        }

        if (!$result instanceof Model && !$result instanceof Collection) {
            throw new IllegalValueException(
                message: 'Invalid data type'
            );
        }

        return $result;
    }

    /**
     * @param class-string $model
     * @return void
     * @throws IllegalTypeException
     * @throws InvalidArgumentException
     */
    public function validateModel(
        string $model,
    ): void {
        if (!class_exists(class: $model)) {
            throw new InvalidArgumentException(
                message: 'Model class does not exist.'
            );
        }

        if (!is_subclass_of(object_or_class: $model, class: Model::class)) {
            throw new IllegalTypeException(
                message: 'Model class is not a subclass of Model.'
            );
        }
    }
}
