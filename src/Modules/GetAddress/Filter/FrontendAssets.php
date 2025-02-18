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
use Resursbank\Ecom\Module\PriceSignage\Widget\CostList;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
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
    /**
     * Enqueued script execution.
     *
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function exec(): void
    {
        // Things that has to be loaded regardless.
        self::enableGenericStyles();
        self::enableGenericJs();

        if (!is_checkout()) {
            return;
        }

        self::enableStyles();
        self::enableScripts();
    }

    /**
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enableGenericStyles(): void
    {
        wp_register_style('rb-costlist-css', false);
        wp_enqueue_style('rb-costlist-css');
        wp_add_inline_style(
            'rb-costlist-css',
            CostList::getCss()
        );
    }

    /**
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enableGenericJs(): void
    {
        wp_register_script(
            'rb-costlist-js',
            '',
            []
        );
        wp_enqueue_script('rb-costlist-js');
        wp_add_inline_script(
            'rb-costlist-js',
            CostList::getJs()
        );
    }

    /**
     * Enable all scripts for the front end.
     *
     * @throws FilesystemException
     * @throws HttpException
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enableScripts(): void
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
                'logLevel' => LogLevel::getData()->name,
                'isUsingCheckoutBlocks' => WooCommerce::isUsingBlocksCheckout()
            ]
        );

        wp_add_inline_script(
            'rb-get-address',
            (string)GetAddress::getWidget()?->js
        );
    }

    /**
     * Enable all styles for the front end.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enableStyles(): void
    {
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
