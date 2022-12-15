<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Exception;
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
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PartPayment;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use WC_Product;

/**
 * Part payment widget
 */
class Module
{
    /** @var PartPayment  */
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
            storeId: StoreId::getData(),
            paymentMethodId: PaymentMethod::getData()
        );

        if ($paymentMethod === null) {
            throw new IllegalTypeException(message: 'Payment method is null');
        }

        $this->amount = (float)$product->get_price();

        $this->instance = new PartPayment(
            storeId: StoreId::getData(),
            paymentMethod: $paymentMethod,
            months: (int)Period::getData(),
            amount: $this->amount,
            currencySymbol: Currency::getWooCommerceCurrencySymbol(),
            currencyFormat: self::getEcomCurrencyFormat(),
            apiUrl: Route::getUrl(route: Route::ROUTE_PART_PAYMENT)
        );
    }

    /**
     *
     *
     * @return CurrencyFormat
     */
    public static function getEcomCurrencyFormat(): CurrencyFormat
    {
        $wooFormat = Currency::getWooCommerceCurrencyFormat();
        if (preg_match(
            pattern: '/\%1\$s.*\%2\$s/',
            subject: $wooFormat
        )) {
            return CurrencyFormat::SYMBOL_FIRST;
        }

        return CurrencyFormat::SYMBOL_LAST;
    }

    /**
     * Output widget HTML if on single product page
     *
     * @return void
     * @throws ConfigException
     */
    public static function getWidget(): void
    {
        if (is_product() && Enabled::isEnabled()) {
            try {
                $widget = new self();
                if ($widget->visible()) {
                    $filtered = apply_filters(
                        hook_name: 'resursbank_partpayment_widget_display',
                        value: Data::getEscapedHtml($widget->instance->content)
                    );

                    if (is_string(value: $filtered)) {
                        echo $filtered;
                    } else {
                        throw new IllegalTypeException(message: 'Filtered widget is no longer a string');
                    }
                }
            } catch (Exception $exception) {
                Config::getLogger()->error(message: $exception);
            }
        }
    }

    /**
     * Output widget CSS if on single product page
     *
     * @return void
     * @throws ConfigException
     */
    public static function setCss(): void
    {
        if (is_product() && Enabled::isEnabled()) {
            try {
                $widget = new self();
                if ($widget->visible()) {
                    $filtered = apply_filters(
                        hook_name: 'resursbank_partpayment_css_display',
                        value: '<style id="rb-pp-styles">' . $widget->instance->css . '</style>'
                    );
                    if (is_string(value: $filtered)) {
                        echo $filtered;
                    } else {
                        throw new IllegalTypeException(message: 'Filtered CSS is no longer a string');
                    }
                }
            } catch (Exception $exception) {
                Config::getLogger()->error(message: $exception);
            }
        }
    }

    /**
     * Set Js if on single product page
     *
     * @return void
     * @throws ConfigException
     */
    public static function setJs(): void
    {
        if (is_product() && Enabled::isEnabled()) {
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
                    add_action(
                        'wp_enqueue_scripts',
                        'partpayment-script'
                    );
                }
            } catch (Exception $exception) {
                Config::getLogger()->error(message: $exception);
            }
        }
    }
}
