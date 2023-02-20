<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
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
use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PartPayment;
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;
use WC_Product;

/**
 * Part payment widget
 */
class Module
{
    private PartPayment $instance;

    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws HttpException
     */
    public function __construct()
    {
        global $product;

        if (!$product instanceof WC_Product) {
            throw new IllegalTypeException(message: 'Unable to fetch product');
        }

        $paymentMethod = Repository::getById(
            storeId: StoreId::getRawData(),
            paymentMethodId: PaymentMethod::getRawData()
        );

        if ($paymentMethod === null) {
            throw new IllegalTypeException(message: 'Payment method is null');
        }

        $this->instance = new PartPayment(
            storeId: StoreId::getRawData(),
            paymentMethod: $paymentMethod,
            months: (int)Period::getRawData(),
            amount: (float)$product->get_price(),
            currencySymbol: Currency::getWooCommerceCurrencySymbol(),
            currencyFormat: self::getEcomCurrencyFormat(),
            apiUrl: Route::getUrl(route: Route::ROUTE_PART_PAYMENT)
        );
    }

    public static function getEcomCurrencyFormat(): CurrencyFormat
    {
        $wooFormat = Currency::getWooCommerceCurrencyFormat();

        if (
            preg_match(pattern: '/\%1\$s.*\%2\$s/', subject: $wooFormat)
        ) {
            return CurrencyFormat::SYMBOL_FIRST;
        }

        return CurrencyFormat::SYMBOL_LAST;
    }

    /**
     * Output widget HTML if on single product page.
     *
     * @throws ConfigException
     */
    public static function getWidget(): void
    {
        self::displayWidgetProperty(propertyName: 'content');
    }

    /**
     * Output widget CSS if on single product page.
     *
     * @throws ConfigException
     */
    public static function setCss(): void
    {
        self::displayWidgetProperty(propertyName: 'css');
    }

    /**
     * Set Js if on single product page.
     * Used from filters.
     *
     * @throws ConfigException
     * @noinspection PhpUnused
     */
    public static function setJs(): void
    {
        if (!is_product() || !Enabled::isEnabled()) {
            return;
        }

        try {
            $widget = new self();

            if ($widget->visible()) {
                /** @psalm-suppress UndefinedConstant */
                $url = Url::getPluginUrl(
                    path: RESURSBANK_MODULE_DIR_NAME . '/js',
                    file: 'js/resursbank_partpayment.js'
                );
                wp_enqueue_script(
                    handle: 'partpayment-script',
                    src: $url,
                    deps: ['jquery']
                );
                wp_add_inline_script(
                    handle: 'partpayment-script',
                    data: $widget->instance->js
                );
                add_action('wp_enqueue_scripts', 'partpayment-script');
            }
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }

    /**
     * Output widget content.
     *
     * @throws ConfigException
     */
    private static function displayWidgetProperty(string $propertyName): void
    {
        if (!is_product() || !Enabled::isEnabled()) {
            return;
        }

        try {
            $widget = new self();

            if ($widget->visible()) {
                $filtered = self::applyFiltersToOutput(
                    propertyName: $propertyName,
                    widget: $widget
                );
                echo Data::getEscapedHtml(content: $filtered);
            }
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }

    /**
     * Apply filters to output.
     *
     * @throws IllegalTypeException
     */
    private static function applyFiltersToOutput(string $propertyName, self $widget): string
    {
        $filtered = apply_filters(
            hook_name: 'resursbank_partpayment_' . $propertyName . '_display',
            value: self::getOutputValue(
                propertyName: $propertyName,
                widget: $widget
            )
        );

        if (!is_string(value: $filtered)) {
            throw new IllegalTypeException(
                message: 'Filtered ' . $propertyName . ' is no longer a string'
            );
        }

        return $filtered;
    }

    /**
     * Get output value.
     */
    private static function getOutputValue(string $propertyName, self $widget): string
    {
        if ($propertyName === 'content') {
            return Data::getEscapedHtml($widget->instance->content);
        }

        if ($propertyName === 'css') {
            return '<style id="rb-pp-styles">' . $widget->instance->css . '</style>';
        }

        return '';
    }

    /**
     * Indicates whether widget should be visible or not.
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @noinspection PhpUnused
     */
    private function visible(): bool
    {
        return Enabled::isEnabled() &&
               $this->instance->getStartingAtCost() >= Limit::getData();
    }
}
