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
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
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
    private static ?EcomPartPayment $instance = null;

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
     */
    public static function getWidget(): EcomPartPayment
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $paymentMethod = Repository::getById(
            storeId: StoreId::getData(),
            paymentMethodId: PaymentMethod::getData()
        );

        if ($paymentMethod === null) {
            throw new IllegalTypeException(message: 'Payment method is null');
        }

        self::$instance = new EcomPartPayment(
            storeId: StoreId::getData(),
            paymentMethod: $paymentMethod,
            months: (int)Period::getData(),
            amount: (float)self::getProduct()->get_price(),
            currencySymbol: Currency::getWooCommerceCurrencySymbol(),
            currencyFormat: Currency::getEcomCurrencyFormat(),
            apiUrl: Route::getUrl(route: Route::ROUTE_PART_PAYMENT),
            decimals: Currency::getConfiguredDecimalPoints()
        );

        return self::$instance;
    }

    /**
     * Init method for frontend scripts and styling.
     *
     * NOTE: Cannot place isEnabled() check here to prevent hooks, product not
     * available yet.
     */
    public static function initFrontend(): void
    {
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
            echo self::getWidget()->content;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Output widget CSS if on single product page.
     */
    public static function setCss(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            $css = self::getWidget()->css;

            echo <<<EX
<style id="rb-pp-styles">
  $css
</style>
EX;
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
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Indicates whether widget should be visible or not.
     */
    private static function isEnabled(): bool
    {
        try {
            return Enabled::isEnabled() &&
                   is_product() &&
                   (float)self::getProduct()->get_price() !== 0.0 &&
                   self::getWidget()->cost->monthlyCost >= Limit::getData();
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
