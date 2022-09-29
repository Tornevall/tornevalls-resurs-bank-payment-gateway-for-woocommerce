<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Api;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * API credentials configuration object.
 */
class Mapi
{
    /**
     * Production endpoint.
     */
    public const URL_PROD = 'https://apigw.resurs.com/api/';

    /**
     * Test endpoint.
     */
    public const URL_TEST = 'https://apigw.integration.resurs.com/api/';

    /**
     * Common prefix route name for all API calls.
     */
    public const COMMON_ROUTE = 'mock_merchant_stores_v2';

    /**
     * Prefix route name for payment based API calls.
     */
    public const PAYMENT_ROUTE = 'mock_merchant_payments_v2';

    /**
     * Prefix route name for payment based API calls.
     */
    public const CUSTOMER_ROUTE = 'mock_merchant_customers_v2';

    /**
     * @param StringValidation $stringValidation
     */
    public function __construct(
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
    }

    /**
     * @param string $route
     * @return string
     * @throws ValidationException
     * @throws EmptyValueException
     */
    public function getUrl(
        string $route
    ): string {
        $this->stringValidation->notEmpty(value: $route);

        return (
            (Config::$instance->isProduction ? self::URL_PROD : self::URL_TEST) .
            $route
        );
    }
}
