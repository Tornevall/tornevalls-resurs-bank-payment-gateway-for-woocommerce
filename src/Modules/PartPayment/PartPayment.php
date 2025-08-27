<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Location;
use Resursbank\Ecom\Lib\Model\PaymentMethod as EcomPaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Widget\PartPayment\Html as EcomPartPayment;
use Resursbank\Ecom\Module\Widget\PartPayment\Js as EcomPartPaymentJs;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled as PartPaymentOptions;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
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
        if (!PartPaymentOptions::isEnabled()) {
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
     * Output widget HTML if on single product page.
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
                paymentMethod: self::getPaymentMethod(),
                months: (int)Period::getData(),
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                displayInfoText: self::displayInfoText(),
                threshold: Limit::getData()
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
                paymentMethod: self::getPaymentMethod(),
                months: (int)Period::getData(),
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                threshold: Limit::getData()
            );

            wp_enqueue_script(
                'partpayment-script',
                Url::getResourceUrl(
                    module: 'PartPayment',
                    file: 'part-payment.js'
                ),
                ['jquery']
            );

            wp_add_inline_script('partpayment-script', $widget->content);
/*
            try {
                $maxApplicationLimit = self::getPaymentMethod()->maxApplicationLimit;
                $minApplicationLimit = self::getPaymentMethod()->minApplicationLimit;
            } catch (Throwable) {
                $minApplicationLimit = 0;
                $maxApplicationLimit = 0;
            }

            // Allow max/min-application limits to render in front end, so
            // that we can hide/show the part payment widget on demand (always rendering,
            // regardless of the threshold).
            wp_localize_script(
                'partpayment-script',
                'rbPpScript',
                [
                    'product_price' => self::getPriceData(),
                    'maxApplicationLimit' => $maxApplicationLimit,
                    'minApplicationLimit' => $minApplicationLimit,
                    'thresholdLimit' => Limit::getData(),
                    'monthlyCost' => $widget->getMonthlyCost(),
                ]
            );
*/
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    public static function getPaymentMethod(): ?EcomPaymentMethod
    {
        if (self::$paymentMethod !== null) {
            return self::$paymentMethod;
        }

        try {
            $paymentMethodSet = PaymentMethod::getData();

            if ($paymentMethodSet === '') {
                throw new EmptyValueException(
                    message: 'Payment method is not properly configured. Part payment view can not be used.'
                );
            }

            self::$paymentMethod = Repository::getById(
                paymentMethodId: $paymentMethodSet
            );

            if (self::$paymentMethod === null) {
                throw new IllegalTypeException(
                    message: "Payment method $paymentMethodSet not found."
                );
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return self::$paymentMethod;
    }

    /**
     * Indicates whether widget should be visible or not.
     */
    public static function isEnabled(): bool
    {
        try {
            $amount = self::getPriceData();
            $method = self::getPaymentMethod();

            // Enabled if there is a product and a price.
            return PartPaymentOptions::isEnabled() &&
                PaymentMethod::getData() !== '' &&
                is_product() &&
                $amount > 0.0 &&
                $amount >= $method->minPurchaseLimit &&
                $amount <= $method->maxPurchaseLimit;
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
