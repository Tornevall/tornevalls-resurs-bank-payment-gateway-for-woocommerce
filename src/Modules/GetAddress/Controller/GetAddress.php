<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

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
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Ecom\Module\Customer\Repository;
use ResursBank\Gateway\ResursDefault;
use Resursbank\Woocommerce\Database\Options\StoreId;
use WC_Session_Handler;

/**
 * Controller to fetch address content.
 */
class GetAddress
{
    /**
     * @return string
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws GetAddressException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws HttpException
     */
    public static function exec(): string
    {
        WC()->initialize_session();

        $controller = new GetAddressController();
        $requestData = $controller->getRequestData();

        // Make sure the session is available before using it, or just skip it.
        if (WC()->session instanceof WC_Session_Handler) {
            WC()->session->set(ResursDefault::PREFIX . Repository::SESSION_KEY_SSN_DATA, $requestData->govId);
        }

        return $controller->exec(
            storeId: StoreId::getData(),
            data: $controller->getRequestData()
        );
    }
}
