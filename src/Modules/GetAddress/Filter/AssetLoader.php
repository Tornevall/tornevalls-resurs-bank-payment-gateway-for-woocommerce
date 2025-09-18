<?php

/** @noinspection PhpArgumentWithoutNamedIdentifierInspection */

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
use Resursbank\Ecom\Module\Widget\CostList\Css as CostListCss;
use Resursbank\Ecom\Module\Widget\CostList\Js as CostListJs;
use Resursbank\Ecom\Module\Widget\PartPayment\Css as EcomPartPaymentCss;
use Resursbank\Ecom\Module\Widget\ReadMore\Css as ReadMoreCss;
use Resursbank\Ecom\Module\Widget\ReadMore\Js as ReadMoreJs;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Queue CSS & JS for the get address widget in frontend checkout. Do not call directly, use WP queues.
 */
class AssetLoader
{
    /**
     * Enqueued script execution.
     *
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws HttpException
     * @throws IllegalValueException
     */
    public static function init(): void
    {
        // Things that have to be loaded regardless.
        self::enqueueCostListStyle();
        self::enqueueCostListJs();
        self::enqueuePartPaymentStyles();
        self::enqueueReadMoreStyle();
        self::enqueueReadMoreJs();

        if (!is_checkout()) {
            return;
        }

        self::enqueueBasicGetAddressStyle();
        self::enqueueGetAddressJs();
    }

    /**
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enqueueCostListStyle(): void
    {
        wp_register_style('rb-costlist-css', false);
        wp_enqueue_style('rb-costlist-css');
        wp_add_inline_style(
            'rb-costlist-css',
            (new CostListCss())->content
        );
    }

    /**
     * Enqueue scripts related to CostList.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enqueueCostListJs(): void
    {
        $costListJs = (new CostListJs(containerElDomPath: 'body'))->content;

        wp_register_script(
            'rb-costlist-js',
            '',
            []
        );
        wp_enqueue_script('rb-costlist-js');
        wp_add_inline_script('rb-costlist-js', $costListJs);
    }

    public static function enqueuePartPaymentStyles(): void
    {
        try {
            $css = (new EcomPartPaymentCss())->content ?? '';
        } catch (Throwable $error) {
            Log::error(error: $error);
            $css = '';
        }

        wp_register_style('rb-pp-styles', false);
        wp_enqueue_style('rb-pp-styles');
        wp_add_inline_style(
            'rb-pp-styles',
            WooCommerce::getRenderedWithNoCrLf(content: $css)
        );

        wp_register_style('rb-pp-css-extra', false);
        wp_enqueue_style('rb-pp-css-extra');
        wp_add_inline_style(
            'rb-pp-css-extra',
            self::getPartPaymentCssExtras()
        );
    }

    /**
     * Enqueue styles related to ReadMore.
     */
    public static function enqueueReadMoreStyle(): void
    {
        try {
            WooCommerce::validateAndUpdatePartPaymentMethod();
            $readMoreCss = (new ReadMoreCss())->content ?? '';
        } catch (Throwable $error) {
            Log::error(error: $error);
            $readMoreCss = '';
        }

        wp_register_style('rb-read-more-style', false);
        wp_enqueue_style('rb-read-more-style');
        wp_add_inline_style(
            'rb-read-more-style',
            WooCommerce::getRenderedWithNoCrLf(content: $readMoreCss)
        );
    }

    /**
     * Enqueue scripts related to ReadMore.
     */
    public static function enqueueReadMoreJs(): void
    {
        if (is_product() && PartPayment::isEnabled()) {
            // Product page js.
            wp_register_script(
                'rb-pp-readmore-js',
                '',
                []
            );
            wp_enqueue_script('rb-pp-readmore-js');
            wp_add_inline_script(
                'rb-pp-readmore-js',
                (new ReadMoreJs(
                    containerElDomPath: '#rb-pp-widget-container'
                ))->content
            );
        }

        if (!is_checkout()) {
            return;
        }

        // Checkout page js.
        wp_register_script(
            'rb-rm-readmore-js',
            '',
            []
        );
        wp_enqueue_script('rb-rm-readmore-js');
        wp_add_inline_script(
            'rb-rm-readmore-js',
            (new ReadMoreJs(containerElDomPath: 'body'))->content
        );
    }

    /**
     * Enable all styles for the front end.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enqueueBasicGetAddressStyle(): void
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

    /**
     * Enable all scripts for the front end.
     *
     * @throws FilesystemException
     * @throws HttpException
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enqueueGetAddressJs(): void
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
            (string)GetAddress::getWidget()?->content
        );
    }

    /**
     * Get the extra CSS for part payment.
     */
    private static function getPartPaymentCssExtras(): string
    {
        return <<<EX
  .rb-usp {
	display: block;
	background-color: rgba(0, 155, 145, 0.8);
	border-radius: 4px;
	padding: 10px;
	color: #fff;
	margin: 0 0 15px 0;
  }
  .rb-ps-cl-container {
    margin-bottom: 10px;
  }
EX;
    }
}
