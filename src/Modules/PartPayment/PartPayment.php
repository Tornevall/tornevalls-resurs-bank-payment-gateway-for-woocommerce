<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Location;
use Resursbank\Ecom\Lib\Model\PaymentMethod as EcomPaymentMethod;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Ecom\Module\Widget\PartPayment\Html as EcomPartPayment;
use Resursbank\Ecom\Module\Widget\PartPayment\Js as EcomPartPaymentJs;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Product;

/**
 * Part payment widget
 */
class PartPayment
{
    /**
     * ECom Part Payment widget instance.
     */
    private static ?EcomPaymentMethod $paymentMethod = null;

    /**
     * Init method for frontend scripts and styling.
     *
     * NOTE: Cannot place isEnabled() check here to prevent hooks, product not
     * available yet.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initFrontend(): void
    {
        if (!UserSettingsRepository::isEnabled(field: Field::PART_PAYMENT_ENABLED)) {
            return;
        }

        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\PartPayment\PartPayment::setJs'
        );
        add_action(
            'woocommerce_single_product_summary',
            'Resursbank\Woocommerce\Modules\PartPayment\PartPayment::renderWidget'
        );
    }

    /**
     * Init method for admin script.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initAdmin(): void
    {
        add_action(
            'admin_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\PartPayment\Admin::setJs'
        );
    }

    /**
     * Output widget HTML if on a single product page.
     *
     * @throws ConfigException
     */
    public static function renderWidget(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        Config::setLocation(
            location: Location::from(value: WooCommerce::getStoreCountry())
        );

        try {
            $widget = new EcomPartPayment(
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                displayInfoText: self::displayInfoText(),
            );

            echo '<div id="rb-pp-widget-container">' .
                $widget->content .
                '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Set Js if on single product page.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function setJs(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            $widget = new EcomPartPaymentJs(
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                )
            );

            wp_enqueue_script(
                'partpayment-script',
                Url::getResourceUrl(
                    module: 'PartPayment',
                    file: 'part-payment.js'
                ),
                ['jquery']
            );

            // Disable this only if you want all front end calculations to break.
            wp_add_inline_script('partpayment-script', $widget->content);
            wp_localize_script(
                'partpayment-script',
                'rbPpScript',
                [
                    'product_price' => self::getPriceData(),
                ]
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Indicates whether widget should be visible or not.
     *
     * @todo Should be moved to Ecom partpayment widget as a trait to be used in CSS, JS and HTML classes.
     */
    public static function isEnabled(): bool
    {
        try {
            $settings = UserSettingsRepository::getSettings();

            $amount = self::getPriceData();
            $method = $settings->partPaymentMethod;

            // Enabled if there is a product and a price.
            return (
                $method !== null &&
                UserSettingsRepository::isEnabled(field: Field::PART_PAYMENT_ENABLED) &&
                is_product() &&
                $amount > 0.0 &&
                $amount >= $method->minPurchaseLimit &&
                $amount <= $method->maxPurchaseLimit
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return false;
    }

    /**
     * Get checkout or product price.
     */
    /**
     * Get checkout or product price, with optional override via filter.
     */
    private static function getPriceData(): float
    {
        try {
            $priceData = is_checkout()
                ? WooCommerce::getCartTotals()
                : (float)self::getProduct()?->get_price();

            // Let partners override.
            $priceDataMaybe = (float)apply_filters(
                'resursbank_pp_price_data',
                $priceData,
                self::getProduct()
            );

            // Only accept positive values from filter.
            if ($priceDataMaybe > 0.0) {
                $priceData = $priceDataMaybe;
            }
        } catch (Throwable) {
            $priceData = WooCommerce::getCartTotals();
        }

        return $priceData;
    }

    /**
     * Programmatically control whether part payment info text should be shown or hidden. Default is to show.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function displayInfoText(): bool
    {
        $returnBool = apply_filters('display_part_payment_info_text', true);
        return is_bool(value: $returnBool) ? $returnBool : false;
    }

    /**
     * @throws IllegalTypeException
     */
    private static function getProduct(): WC_Product
    {
        global $product;

        if (!$product instanceof WC_Product) {
            $product = wc_get_product();
        }

        if (!$product instanceof WC_Product) {
            throw new IllegalTypeException(message: 'Unable to fetch product');
        }

        return $product;
    }
}
