<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Util\Url;

class GetAddress
{
	/**
	 * @return void
	 */
	public static function exec(): void
	{
		$controller = new GetAddressController();

		try {
			$controller->exec(
				storeId: StoreId::getData(),
				data: $controller->getRequestData()
			);
		} catch (HttpException $e) {
			$controller->respondWithError(exception: $e);
		}
	}
}
