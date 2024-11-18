<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout;
use Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout as Widget;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Url;

/**
 * Implementation of get address widget in checkout.
 */
class GetAddress
{
    /**
     * Initialize module.
     */
    public static function setup(): void
    {
        Widget::register();
    }

	public static function init(): void
	{
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_enqueue_script(
					'rb-get-address',
					Url::getAssetUrl(file: 'update-address.js'),
					['wp-data', 'jquery', 'wc-blocks-data-store'],
					'1.0.0',
					true // Load script in footer.
				);

				wp_add_inline_script(
					'rb-get-address',
					(string) Checkout::getWidget()?->js
				);

				wp_enqueue_style(
					'rb-ga-css',
					Url::getResourceUrl(
						module: 'GetAddress',
						file: 'blocks.css',
						type: ResourceType::CSS
					),
					[],
					'1.0.0'
				);
			}
		);
	}
}
