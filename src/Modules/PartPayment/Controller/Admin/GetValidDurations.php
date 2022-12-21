<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment\Controller\Admin;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\AnnuityFactor\Http\DurationsByMonthController;
use Resursbank\Woocommerce\Database\Options\StoreId;

/**
 * Controller for fetching valid duration options for a specified payment method
 */
class GetValidDurations
{
    /**
     * @throws HttpException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     */
    public static function exec(): string
    {
        $controller = new DurationsByMonthController();
        $requestData = $controller->getRequestData();
        $paymentMethodId = $requestData->paymentMethodId;
        $storeId = StoreId::getData();

        return $controller->exec(
            storeId: $storeId,
            paymentMethodId: $paymentMethodId
        );
    }
}
