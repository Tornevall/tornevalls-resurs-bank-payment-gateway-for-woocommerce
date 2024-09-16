<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PartPayment as EcomPartPayment;
use Resursbank\Ecom\Module\PaymentMethod\Widget\ReadMore;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled as PartPaymentOptions;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Util\Currency;
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
    public static ?ReadMore $readMoreInstance = null;

    /**
     * ECom Part Payment widget instance.
     */
    private static ?EcomPartPayment $instance = null;

    /**
     * @throws TranslationException
     * @throws ValidationException
     * @throws CurlException
     * @throws IllegalValueException
     * @throws IllegalTypeException
     * @throws Throwable
     * @throws EmptyValueException
     * @throws AuthException
     * @throws JsonException
     * @throws ConfigException
     * @throws ReflectionException
     * @throws ApiException
     * @throws CacheException
     * @throws FilesystemException
     */
    public static function getReadMoreWidget(): ReadMore
    {
        if (self::$readMoreInstance !== null) {
            return self::$readMoreInstance;
        }

        $paymentMethodSet = PaymentMethod::getData();

        if ($paymentMethodSet === '') {
            throw new EmptyValueException(
                message: 'Payment method is not properly configured. Part payment view can not be used.'
            );
        }

        $paymentMethod = Repository::getById(
            storeId: StoreId::getData(),
            paymentMethodId: PaymentMethod::getData()
        );

        self::$readMoreInstance = new ReadMore(
            paymentMethod: $paymentMethod,
            amount: self::getPriceData()
        );

        return self::$readMoreInstance;
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws HttpException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @throws Throwable
     */
    public static function getWidget(): ?EcomPartPayment
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $priceData = self::getPriceData();

        if ($priceData <= 0.0) {
            return null;
        }

        $paymentMethodSet = PaymentMethod::getData();

        if ($paymentMethodSet === '') {
            throw new EmptyValueException(
                message: 'Payment method is not properly configured. Part payment view can not be used.'
            );
        }

        $paymentMethod = Repository::getById(
            storeId: StoreId::getData(),
            paymentMethodId: PaymentMethod::getData()
        );

        if ($paymentMethod === null) {
            throw new IllegalTypeException(
                message: "Payment method $paymentMethodSet not found."
            );
        }

        if (
            $priceData >= $paymentMethod->minPurchaseLimit &&
            $priceData <= $paymentMethod->maxPurchaseLimit
        ) {
            self::$instance = new EcomPartPayment(
                storeId: StoreId::getData(),
                paymentMethod: $paymentMethod,
                months: (int)Period::getData(),
                amount: $priceData,
                currencySymbol: Currency::getWooCommerceCurrencySymbol(),
                currencyFormat: Currency::getEcomCurrencyFormat(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                decimals: Currency::getConfiguredDecimalPoints(),
                displayInfoText: self::displayInfoText(),
                threshold: Limit::getData()
            );

            return self::$instance;
        }

        return null;
    }

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
        if (
            !PartPaymentOptions::isEnabled() ||
            PaymentMethod::getData() === ''
        ) {
            return;
        }

        add_action(
            'wp_head',
            'Resursbank\Woocommerce\Modules\PartPayment\PartPayment::setCss'
        );
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

        try {
            echo '<div id="rb-pp-widget-container">' . self::getWidget()->content . self::getReadMoreWidget()->content . '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Output widget CSS if on single product page.
     */
    public static function setCss(): void
    {
        // No price, not widget.
        if (self::getPriceData() <= 0.0) {
            return;
        }

        try {
            $css = self::getWidget()->css ?? '';
            $readMoreCss = self::getReadMoreWidget()->css ?? '';

            echo <<<EX
<style id=" rb-pp-styles">
  $css
  $readMoreCss
</style>
EX;
        } catch (EmptyValueException) {
            // Take no action when payment method is not set.
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
            wp_enqueue_script(
                'partpayment-script',
                Url::getScriptUrl(
                    module: 'PartPayment',
                    file: 'part-payment.js'
                ),
                ['jquery']
            );
            wp_add_inline_script(
                'partpayment-script',
                self::getWidget()->js
            );
            add_action('wp_enqueue_scripts', 'partpayment-script');

            try {
                $maxApplicationLimit = self::getWidget()->paymentMethod->maxApplicationLimit;
                $minApplicationLimit = self::getWidget()->paymentMethod->minApplicationLimit;
            } catch (Throwable $error) {
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
                    'monthlyCost' => self::getWidget()->getMonthlyCost()
                ]
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Get checkout or product price.
     */
    private static function getPriceData(): float
    {
        try {
            $priceData = is_checkout()
                ? WooCommerce::getCartTotals()
                : (float)self::getProduct()?->get_price();
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
     * Indicates whether widget should be visible or not.
     */
    private static function isEnabled(): bool
    {
        try {
            // Enabled if there is a product and a price.
            return PartPaymentOptions::isEnabled() &&
                PaymentMethod::getData() !== '' &&
                is_product() &&
                self::getPriceData() > 0.0;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return false;
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
