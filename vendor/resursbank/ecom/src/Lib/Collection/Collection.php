<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Collection;

use ArrayAccess;
use Iterator;
use Countable;
use Resursbank\Ecom\Exception\CollectionException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;

use function is_object;

/**
 * Base collection class
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Collection implements ArrayAccess, Iterator, Countable
{
    private const TYPE_ERR = 'Collection requires data to be of type %s, received %s';
    private const TYPE_ERR_NO_DATA = 'No type or data specified';

    protected string $type;
    private array $data;
    private int $position;

    /**
     * @param array $data
     * @param string|null $type
     * @throws IllegalTypeException
     */
    public function __construct(array $data, string $type = null)
    {
        $type = $this->determineType(data: $data, type: $type);
        $this->verifyDataArrayType(data: $data, type: $type);
        $this->data = $data;
        $this->type = $type;
        $this->position = 0;
    }

    /**
     * Get collection from specified type or first element of data array
     *
     * @param array $data
     * @param string|null $type
     * @return string
     * @throws IllegalTypeException
     */
    private function determineType(array $data, string $type = null): string
    {
        if ($type) {
            return $type;
        }

        if (!empty($data) && isset($data[0])) {
            return is_object(value: $data[0]) ? $data[0]::class : gettype(value: $data[0]);
        }

        throw new IllegalTypeException(message: self::TYPE_ERR_NO_DATA);
    }

    /**
     * Verify the type of objects in collection data
     *
     * @param array $data
     * @param string $type
     * @return void
     * @throws IllegalTypeException
     */
    private function verifyDataArrayType(array $data, string $type): void
    {
        /** @psalm-suppress MixedAssignment */
        foreach ($data as $item) {
            if (
                (is_object(value: $item) && $item::class !== $type) ||
                (!is_object(value: $item) && gettype(value: $item) !== $type)
            ) {
                throw new IllegalTypeException(
                    message: sprintf(
                        self::TYPE_ERR,
                        $type,
                        (is_object(value: $item) ? $item::class : gettype(value: $item))
                    )
                );
            }
        }
    }

    /**
     * Set new data array
     *
     * @param array $data
     * @return void
     * @throws IllegalTypeException
     */
    public function setData(array $data): void
    {
        $this->verifyDataArrayType(
            data: $data,
            type: $this->type
        );

        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get collection type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get data array from collection
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @throws IllegalTypeException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (
            (is_object(value: $value) && $value::class !== $this->type) ||
            (!is_object(value: $value) && gettype(value: $value) !== $this->type)
        ) {
            throw new IllegalTypeException(
                message: sprintf(
                    self::TYPE_ERR,
                    $this->type,
                    is_object(value: $value) ? $value::class : gettype(value: $value)
                )
            );
        }

        if ($offset === null) {
            $this->data[] = $value;
        } else {
            /** @psalm-suppress MixedArrayOffset */
            $this->data[$offset] = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        /** @psalm-suppress MixedArrayOffset */
        return isset($this->data[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        /** @psalm-suppress MixedArrayOffset */
        unset($this->data[$offset]);
    }

    /**
     * @inheritDoc
     * @psalm-suppress MixedArrayOffset
     * @psalm-suppress MixedReturnStatement
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!isset($this->data[$offset])) {
            $this->data[$offset] = null;
        }

        return $this->data[$offset];
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @inheritDoc
     * @throws CollectionException
     */
    public function current(): mixed
    {
        if (!isset($this->data[$this->position])) {
            throw new CollectionException(
                message: 'Could not find any data in data array.'
            );
        }
        return $this->data[$this->position];
    }

    /**
     * @inheritDoc
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    public function key(): mixed
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
}
