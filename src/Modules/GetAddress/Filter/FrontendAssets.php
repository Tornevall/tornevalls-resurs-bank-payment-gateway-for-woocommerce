<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter;

use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Queue CSS & JS for the get address widget in frontend checkout.
 */
class FrontendAssets
{
    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
    /**
     * @throws HttpException
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @throws FilesystemException
     */
    public static function exec(): void
    {
        wp_enqueue_script(
            'rb-get-address',
            Url::getAssetUrl(file: 'update-address.js'),
            ['wp-data', 'jquery', 'wc-blocks-data-store'],
            WooCommerce::getAssetVersion(assetFile: 'update-address'),
            // Load script in footer.
            true
        );

        wp_localize_script(
            'rb-get-address',
            'rbFrontendData',
            [
                'currentCustomerType' => WcSession::getCustomerType(),
                'apiUrl' => Route::getUrl(
                    route: Route::ROUTE_SET_CUSTOMER_TYPE
                ),
                'getAddressEnabled' => EnableGetAddress::isEnabled(),
            ]
        );

        wp_add_inline_script(
            'rb-get-address',
            (string)GetAddress::getWidget()?->js
        );

        try {
            wp_enqueue_style(
                'rb-ga-basic-css',
                Route::getUrl('get-address-css'),
                [],
                '1.0.0'
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        wp_enqueue_style(
            'rb-ga-css',
            Url::getResourceUrl(
                module: 'GetAddress',
                file: 'custom.css',
                type: ResourceType::CSS
            ),
            [],
            '1.0.0'
        );
    }
}
