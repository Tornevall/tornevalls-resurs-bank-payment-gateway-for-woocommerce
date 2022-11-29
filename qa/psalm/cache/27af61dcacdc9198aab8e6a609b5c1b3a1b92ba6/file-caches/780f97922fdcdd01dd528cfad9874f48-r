<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Store;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;
use Resursbank\Ecom\Lib\Repository\Api\Mapi\Get;
use Exception;
use Resursbank\Ecom\Lib\Repository\Cache;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Models\StoreCollection;

/**
 * Interaction with Store entities and related functionality.
 */
class Repository
{
    use ExceptionLog;

    /**
     * @param int $size
     * @param int|null $page
     * @param array $sort
     * @return StoreCollection
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function getStores(
        int $size = 999999,
        ?int $page = null,
        array $sort = []
    ): StoreCollection {
        try {
            $cache = self::getCache(size: $size, page: $page, sort: $sort);
            $result = $cache->read();

            if (!$result instanceof StoreCollection) {
                $result = self::getApi(size: $size, page: $page, sort: $sort)->call();

                if (!$result instanceof StoreCollection) {
                    throw new ApiException(message: 'Invalid API response.');
                }

                $cache->write(data: $result);
            }
        } catch (Exception $e) {
            self::logException(exception: $e);

            throw $e;
        }

        return $result;
    }

    /**
     * @param int $size
     * @param int|null $page
     * @param array $sort
     * @return Cache
     */
    public static function getCache(
        int $size = 999999,
        ?int $page = null,
        array $sort = []
    ): Cache {
        return new Cache(
            key: 'stores-' . sha1(
                string: serialize(value: compact('size', 'page', 'sort'))
            ),
            model: Store::class,
            ttl: 3600
        );
    }

    /**
     * @param int $size
     * @param int|null $page
     * @param array $sort
     * @return Get
     * @throws IllegalTypeException
     */
    public static function getApi(
        int $size = 999999,
        ?int $page = null,
        array $sort = []
    ): Get {
        return new Get(
            model: Store::class,
            route: Mapi::STORE_ROUTE,
            params: compact('size', 'page', 'sort'),
            extractProperty: 'content'
        );
    }
}
