<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Model\Callback\GetAddressRequest;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

/**
 * Get customer address.
 */
class GetAddress
{
	/**
	 * @throws ConfigException
	 * @throws HttpException
	 */
	public static function exec(): string
	{
		$controller = new GetAddressController();
		$requestData = $controller->getRequestData();

		try {
			self::updateSessionData(data: $requestData);

			$return = $controller->exec(data: $requestData);
		} catch (Throwable $e) {
			// Do nothing.
			Config::getLogger()->debug(message: $e);
		}

		return $return ?? '{}';
	}

	/**
	 * Update selected customer type and submitted SSN (supplied when using the
	 * fetch address widget at checkout). These values will later be submitted
	 * to Resurs Bank to speed up the gateway procedure. Note that submitting
	 * these values to Resurs Bank is not a requirement for everything to work.
	 */
	private static function updateSessionData(
		GetAddressRequest $data
	): void {
		$ecomSession = new Session();

		WcSession::set(
			key: $ecomSession->getKey(key: Repository::SESSION_KEY_SSN_DATA),
			value: $data->govId
		);

		WcSession::set(
			key: $ecomSession->getKey(
				key: Repository::SESSION_KEY_CUSTOMER_TYPE
			),
			value: $data->customerType->value
		);
	}
}
