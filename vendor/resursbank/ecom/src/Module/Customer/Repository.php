<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Customer;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;
use Resursbank\Ecom\Module\Customer\Api\GetAddress;
use Resursbank\Ecom\Lib\Order\CustomerType;

/**
 * Customer repository.
 */
class Repository
{
    use ExceptionLog;

    /**
     * @param string $storeId
     * @param string $governmentId
     * @param CustomerType $customerType
     * @param GetAddress $api
     * @return Address
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws GetAddressException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws ApiException
     * @throws ConfigException
     */
    public static function getAddress(
        string $storeId,
        string $governmentId,
        CustomerType $customerType,
        GetAddress $api = new GetAddress()
    ): Address {
        try {
            return $api->call(
                storeId: $storeId,
                governmentId: $governmentId,
                customerType: $customerType
            );
        } catch (Exception $e) {
            self::logException(exception: $e);

            throw $e;
        }
    }
}
