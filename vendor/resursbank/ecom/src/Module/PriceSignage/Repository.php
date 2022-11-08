<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PriceSignage;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;
use Resursbank\Ecom\Lib\Repository\Api\Mapi\Get;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Exception;
use Resursbank\Ecom\Module\PriceSignage\Models\Cost;
use Resursbank\Ecom\Module\PriceSignage\Models\CostCollection;
use Resursbank\Ecom\Module\PriceSignage\Models\PriceSignage;
use Resursbank\Ecom\Lib\Repository\Cache;

/**
 * Interaction with Payment Method entities and related functionality.
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
     * @param float $amount
     * @param int|null $monthFilter
     * @return PriceSignage
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
     * @throws ConfigException
     */
    public static function getPriceSignage(
        string $storeId,
        string $paymentMethodId,
        float $amount,
        ?int $monthFilter = null
    ): PriceSignage {
        try {
            $cache = self::getCache(
                storeId: $storeId,
                paymentMethodId: $paymentMethodId,
                amount: $amount,
                monthFilter: $monthFilter
            );
            $result = $cache->read();

            if (!$result instanceof PriceSignage) {
                $result = self::getApi(
                    storeId: $storeId,
                    paymentMethodId: $paymentMethodId,
                    amount: $amount
                )->call();

                if (!$result instanceof PriceSignage) {
                    throw new ApiException(message: 'Invalid API response.');
                }

                if ($monthFilter !== null) {
                    $result = self::filterResultByMonth(
                        result: $result,
                        monthFilter: $monthFilter
                    );
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
     * @param string $paymentMethodId
     * @param float $amount
     * @param int|null $monthFilter
     * @return Cache
     * @throws IllegalValueException
     */
    public static function getCache(
        string $storeId,
        string $paymentMethodId,
        float $amount,
        ?int $monthFilter = null
    ): Cache {
        self::validateStoreId(storeId: $storeId);
        self::validatePaymentMethodId(paymentMethodId: $paymentMethodId);

        return new Cache(
            key: 'price-signage-' . sha1(
                string: serialize(
                    value: compact(
                        'storeId',
                        'paymentMethodId',
                        'amount',
                        'monthFilter'
                    )
                )
            ),
            model: PriceSignage::class,
            ttl: 3600
        );
    }

    /**
     * @todo If $amount is less than paymentMethod minimum purchase limit we get 401 atm.
     *
     * @param string $storeId
     * @param string $paymentMethodId
     * @param float $amount
     * @return Get
     * @throws IllegalValueException|IllegalTypeException
     */
    public static function getApi(
        string $storeId,
        string $paymentMethodId,
        float $amount
    ): Get {
        self::validateStoreId(storeId: $storeId);
        self::validatePaymentMethodId(paymentMethodId: $paymentMethodId);

        return new Get(
            model: PriceSignage::class,
            route: Mapi::STORE_ROUTE . '/' . $storeId . '/payment_methods/' . $paymentMethodId . '/price_signage',
            params: ['amount' => $amount]
        );
    }

    /**
     * @param PriceSignage $result
     * @param int $monthFilter
     * @return PriceSignage
     * @throws IllegalTypeException
     */
    private static function filterResultByMonth(
        PriceSignage $result,
        int $monthFilter
    ): PriceSignage {
        $costs = array_filter(
            array: $result->costList->toArray(),
            callback: static fn ($cost) => $cost instanceof Cost && $cost->months === $monthFilter
        );

        return new PriceSignage(
            secciLinks: $result->secciLinks,
            generalTermsLinks: $result->generalTermsLinks,
            costList: new CostCollection(data: $costs)
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

    /**
     * @param string $paymentMethodId
     * @return void
     * @throws IllegalValueException
     */
    private static function validatePaymentMethodId(
        string $paymentMethodId
    ): void {
        $stringValidation = new StringValidation();
        $stringValidation->isUuid(value: $paymentMethodId);
    }
}
