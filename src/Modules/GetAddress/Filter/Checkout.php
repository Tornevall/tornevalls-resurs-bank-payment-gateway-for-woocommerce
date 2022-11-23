<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Customer\Widget\GetAddress;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;

/**
 * Render get address form above the form on the checkout page.
 */
class Checkout
{
	/**
	 * @return void
	 */
	public static function register(): void
	{
		add_filter(
			'woocommerce_before_checkout_form',
			function () { self::exec(); }
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script(
					handle: 'rb-get-address',
					src: Url::getScriptUrl(
						module: 'GetAddress',
						file: 'get-address.js'
					)
				);
			}
		);
	}

	/**
	 * @return void
	 */
	public static function exec(): void
	{
		$result = '';

		try {
			$address = new GetAddress(
				fetchUrl: Route::getUrl(route: Route::ROUTE_GET_ADDRESS)
			);

			$result = $address->content;
		} catch (Exception $e) {
			try {
				Config::getLogger()->error(message: $e);
			} catch (ConfigException) {
				$result = 'Resursbank: failed to render get address widget.';
			}
		}

		echo $result;
	}
}
