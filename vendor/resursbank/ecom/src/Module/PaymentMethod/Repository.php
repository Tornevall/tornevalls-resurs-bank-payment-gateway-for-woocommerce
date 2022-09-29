<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod;

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
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;
use Resursbank\Ecom\Lib\Repository\Api\Mapi\Get;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Exception;
use Resursbank\Ecom\Module\PaymentMethod\Models\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Models\PaymentMethodCollection;
use Resursbank\Ecom\Lib\Repository\Cache;

/**
 * Interaction with Payment Method entities and related functionality.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Repository
{
    use ExceptionLog;

    /**
     * NOTE: Parameters must be validated since they are utilized for our cache
     * keys.
     *
     * @param string $storeId
     * @param float|null $amount
     * @return PaymentMethodCollection
     * @throws ApiException
     * @throws CacheException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     */
    public static function getPaymentMethods(
        string $storeId,
        ?float $amount = null
    ): PaymentMethodCollection {
        try {
            $cache = self::getCache(storeId: $storeId, amount: $amount);
            $result = $cache->read();

            if (!$result instanceof PaymentMethodCollection) {
                $result = self::getApi(storeId: $storeId, amount: $amount)->call();

                if (!$result instanceof PaymentMethodCollection) {
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
     * @param string $storeId
     * @param float|null $amount
     * @return Cache
     * @throws IllegalValueException
     */
    public static function getCache(
        string $storeId,
        ?float $amount = null
    ): Cache {
        self::validateStoreId(storeId: $storeId);

        return new Cache(
            key: 'payment-methods-' . sha1(
                string: serialize(value: compact('storeId', 'amount'))
            ),
            model: PaymentMethod::class,
            ttl: 3600
        );
    }

    /**
     * @param string $storeId
     * @param float|null $amount
     * @return Get
     * @throws IllegalValueException|IllegalTypeException
     */
    public static function getApi(
        string $storeId,
        ?float $amount = null
    ): Get {
        self::validateStoreId(storeId: $storeId);

        return new Get(
            model: PaymentMethod::class,
            route: "stores/$storeId/payment_methods",
            params: compact('storeId', 'amount'),
            extractProperty: 'content'
        );
    }

    /**
     * @param string $storeId
     * @return void
     * @throws IllegalValueException
     */
    private static function validateStoreId(
        string $storeId
    ): void {
        $stringValidation = new StringValidation();
        $stringValidation->isUuid(value: $storeId);
    }
}
