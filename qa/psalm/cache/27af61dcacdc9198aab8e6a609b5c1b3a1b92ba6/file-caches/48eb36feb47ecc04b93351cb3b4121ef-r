<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Repository;

use InvalidArgumentException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Cache\AbstractCache;
use Resursbank\Ecom\Lib\Collection\Collection;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Repository\Traits\ModelConverter;
use Exception;
use TypeError;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;

/**
 * Generic functionality for cache repository implementations.
 */
class Cache
{
    use ExceptionLog;
    use ModelConverter;

    /**
     * @param string $key | Keys will be decorated using AbstractCache::getKey()
     * @param class-string $model | Convert cached data to model instance(s).
     * @param int $ttl | Timeout in seconds before cache expires (0=never).
     */
    public function __construct(
        public readonly string $key,
        private readonly string $model,
        private readonly int $ttl = 3600
    ) {
    }

    /**
     * @return null|Collection|Model
     * @throws CacheException
     * @throws ConfigException
     * @todo Check if ConfigException validation needs a test.
     */
    public function read(): null|Collection|Model
    {
        $result = null;

        $data = Config::getCache()->read(
            key: AbstractCache::getKey(key: $this->key)
        );

        try {
            if ($data !== null) {
                $result = $this->convertToModel(
                    data: $data,
                    model: $this->model
                );

                if ($result instanceof Collection && count($result) === 0) {
                    $result = null;
                }
            }
        } catch (TypeError | Exception $e) {
            throw new CacheException(
                message: 'Failed reading from cache.',
                previous: $e
            );
        }

        return $result;
    }

    /**
     * @return void
     * @throws ConfigException
     */
    public function clear(): void
    {
        Config::getCache()->clear(
            key: AbstractCache::getKey(key: $this->key)
        );
    }

    /**
     * @param Collection|Model $data
     * @throws CacheException
     */
    public function write(
        Collection|Model $data
    ): void {
        try {
            // Validate model type.
            if (
                $data instanceof Model &&
                !($data instanceof $this->model)
            ) {
                throw new InvalidargumentException(
                    message: "Invalid model type, expected $this->model"
                );
            }

            // Validate collection type.
            if (
                $data instanceof Collection &&
                ($data->getType() !== $this->model)
            ) {
                throw new InvalidargumentException(
                    message: "Invalid collection type, expected $this->model"
                );
            }

            // Write cache.
            Config::getCache()->write(
                key: AbstractCache::getKey(key: $this->key),
                data: json_encode(
                    value: $data->toArray(full: true),
                    flags: JSON_THROW_ON_ERROR
                ),
                ttl: $this->ttl
            );
        } catch (TypeError | Exception $e) {
            throw new CacheException(
                message: 'Failed writing to cache.',
                previous: $e
            );
        }
    }
}
