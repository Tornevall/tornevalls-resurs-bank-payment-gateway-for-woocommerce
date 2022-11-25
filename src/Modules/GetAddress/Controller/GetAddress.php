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
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Util\Url;

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
		$controller = new GetAddressController();

		return $controller->exec(
			storeId: StoreId::getData(),
			data: $controller->getRequestData()
		);
	}
}
