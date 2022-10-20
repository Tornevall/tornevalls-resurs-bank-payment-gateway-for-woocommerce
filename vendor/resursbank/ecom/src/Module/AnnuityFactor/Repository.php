<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\AnnuityFactor;

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
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Lib\Repository\Api\Mapi\Get;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Exception;
use Resursbank\Ecom\Module\AnnuityFactor\Models\AnnuityFactors;
use Resursbank\Ecom\Lib\Repository\Cache;

/**
 * Interaction with Annuity factor entities and related functionality.
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
     * @param string $paymentMethodId
     * @return AnnuityFactors
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
    public static function getAnnuityFactors(
        string $storeId,
        string $paymentMethodId
    ): AnnuityFactors {
        try {
            $cache = self::getCache(
                storeId: $storeId,
                paymentMethodId: $paymentMethodId
            );

            $result = $cache->read();

            if (!$result instanceof AnnuityFactors) {
                $result = self::getApi(
                    storeId: $storeId,
                    paymentMethodId: $paymentMethodId
                )->call();

                if (!$result instanceof AnnuityFactors) {
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
     * @param PaymentMethodCollection $paymentMethods
     * @return PaymentMethodCollection
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
    public static function getMethods(
        string $storeId,
        PaymentMethodCollection $paymentMethods
    ): PaymentMethodCollection {
        /** @var PaymentMethod[] $arr */
        $arr = $paymentMethods->toArray();

        /** @var PaymentMethod[] $result */
        $result = [];

        foreach ($arr as $method) {
            $factors = self::getAnnuityFactors(
                storeId: $storeId,
                paymentMethodId: $method->id
            );

            if ($factors->content->count() !== 0) {
                $result[] = $method;
            }
        }

        return new PaymentMethodCollection(data: $result);
    }

    /**
     * @param string $storeId
     * @param string $paymentMethodId
     * @return Cache
     * @throws IllegalValueException
     */
    public static function getCache(
        string $storeId,
        string $paymentMethodId
    ): Cache {
        self::validateStoreId(storeId: $storeId);

        return new Cache(
            key: 'payment-method-annuity' . sha1(
                string: serialize(value: compact('storeId', 'paymentMethodId'))
            ),
            model: AnnuityFactors::class,
            ttl: 3600
        );
    }

    /**
     * @param string $storeId
     * @param string $paymentMethodId
     * @return Get
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public static function getApi(
        string $storeId,
        string $paymentMethodId
    ): Get {
        self::validateStoreId(storeId: $storeId);

        return new Get(
            model: AnnuityFactors::class,
            route: "stores/$storeId/payment_methods/$paymentMethodId/annuity_factors",
            params: compact('storeId', 'paymentMethodId'),
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
