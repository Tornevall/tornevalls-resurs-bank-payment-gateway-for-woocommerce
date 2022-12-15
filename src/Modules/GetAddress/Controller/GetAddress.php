<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Util\WcSession;

/**
 * Controller to fetch address content.
 */
class GetAddress
{
    /**
     * @return string
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws GetAddressException
     * @throws HttpException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function exec(): string
    {
        $controller = new GetAddressController();
        $requestData = $controller->getRequestData();

        $ecomSession = new Session();
        try {
            WcSession::set(
                $ecomSession->getKey(key: Repository::SESSION_KEY_SSN_DATA),
                $requestData->govId
            );
            WcSession::set(
                $ecomSession->getKey(key: Repository::SESSION_KEY_CUSTOMER_TYPE),
                $requestData->customerType->value
            );
        } catch (Exception) {
            // Do nothing.
        }

        return $controller->exec(
            storeId: StoreId::getData(),
            data: $requestData
        );
    }
}