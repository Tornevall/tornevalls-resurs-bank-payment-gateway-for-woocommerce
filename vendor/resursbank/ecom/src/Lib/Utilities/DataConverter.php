<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities;

use ArgumentCountError;
use ReflectionClass;
use ReflectionObject;
use ReflectionNamedType;
use ReflectionException;
use Resursbank\Ecom\Exception\TypeException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

use Resursbank\Ecom\Lib\Model\Model;
use function call_user_func;
use function is_object;

/**
 * Utility class for data type conversions.
 */
class DataConverter
{
    /**
     * Converts stdClass objects to specified type.
     *
     * NOTE: The intention is that the conversion class itself validates
     * assigned values through its constructor.
     *
     * @param object $object
     * @param class-string $type
     * @return Model
     * @throws ReflectionException
     * @throws ArgumentCountError
     * @throws IllegalTypeException
     * @psalm-suppress MixedAssignment
     * @psalm-suppress InvalidNamedArgument
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress MixedMethodCall
     */
    public static function stdClassToType(object $object, string $type): Model
    {
        $sourceReflection = new ReflectionObject(object: $object);
        $destReflection = new ReflectionClass(objectOrClass: $type);
        $sourceProperties = $sourceReflection->getProperties();
        $arguments = [];
        foreach ($sourceProperties as $sourceProperty) {
            /** @noinspection PhpExpressionResultUnusedInspection */
            $sourceProperty->setAccessible(accessible: true);
            $name = $sourceProperty->getName();
            $value = $sourceProperty->getValue(object: $object);

            if ($destReflection->hasProperty(name: $name)) {
                $destinationProperty = $destReflection->getProperty(
                    name: $name
                );
                /** @var ReflectionNamedType $destinationType */
                $destinationType = $destinationProperty->getType();
                $propertyType = $destinationType->getName();

                // If our property is a collection we need to take the value array and convert all items individually
                // before loading our new collection object
                if (is_subclass_of(object_or_class: $propertyType, class: Collection::class)) {
                    $converted = [];
                    $dummyCollection = new $propertyType(data: []);
                    $dummyCollectionMemberType = $dummyCollection->getType();
                    foreach ($value as $item) {
                        $converted[] = self::stdClassToType(
                            object: $item,
                            type: $dummyCollectionMemberType
                        );
                    }
                    $dummyCollection->setData(data: $converted);
                    $arguments[$name] = $dummyCollection;
                } elseif (enum_exists($propertyType)) {
                    // If our property is an enum we need to convert the value
                    // to the enum value it represents.
                    $arguments[$name] = call_user_func(
                        $propertyType . '::from',
                        /** @psalm-suppress MixedPropertyFetch */
                        is_object($value) ? $value->value : $value
                    );
                } elseif (is_object(value: $value)) {
                    $arguments[$name] = self::stdClassToType(
                        object: $value,
                        type: $propertyType
                    );
                } else {
                    $arguments[$name] = $value;
                }
            }
        }

        return new $type(...$arguments);
    }

    /**
     * @param array $data
     * @param class-string $targetType
     * @return Collection
     * @throws ReflectionException
     * @throws IllegalTypeException
     */
    public static function arrayToCollection(array $data, string $targetType): Collection
    {
        $convertedData = [];
        foreach ($data as $item) {
            $convertedData[] = self::stdClassToType(
                object: $item,
                type: $targetType
            );
        }
        return new ($targetType . 'Collection')(data: $convertedData);
    }
}