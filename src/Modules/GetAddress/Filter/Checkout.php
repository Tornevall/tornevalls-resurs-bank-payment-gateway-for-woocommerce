<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Customer\Widget\GetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Render get address form above the form on the checkout page.
 */
class Checkout
{
    private static ?GetAddress $instance = null;

    /**
     * @throws HttpException
     * @throws IllegalValueException
     * @throws FilesystemException
     */
    public static function getWidget(): ?GetAddress
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new GetAddress(
            url: Route::getUrl(route: Route::ROUTE_GET_ADDRESS)
        );

        return self::$instance;
    }

    /**
     * Register filter subscribers
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function register(): void
    {
        if (!EnableGetAddress::getData()) {
            return;
        }

        add_action(
            'wp_head',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout::setCss'
        );

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        add_filter(
            'woocommerce_before_checkout_form',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout::exec'
        );

        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout::loadScripts'
        );
    }

    /**
     * Sets up getAddress CSS.
     */
    public static function setCss(): void
    {
        try {
            $css = self::getWidget()->css ?? '';
            echo <<<EX
<style id=" rb-getaddress-styles">
  $css
</style>
EX;
        } catch (EmptyValueException) {
            // Take no action when payment method is not set.
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Loads script and stylesheet for form.
     *
     * @throws FilesystemException
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function loadScripts(): void
    {
        wp_enqueue_script(
            'rb-get-address',
            Url::getScriptUrl(
                module: 'GetAddress',
                file: 'getAddressForm.js'
            ),
            ['rb-set-customertype']
        );

        wp_add_inline_script(
            'rb-get-address',
            self::getWidget()->js
        );
    }

    /**
     * Renders and returns the content of the widget that fetches the customer
     * address.
     */
    public static function exec(): void
    {
        $result = '';

        try {
            $address = new GetAddress(
                url: Route::getUrl(route: Route::ROUTE_GET_ADDRESS)
            );

            /**
             * Create compatibility with template paragraphing when wpautop is executed.
             * This script works properly when themes keeps the script code separate
             * from other html-data. When this is not happening, our scripts
             * will be treated as html, and the <p>-tags are wrongfully added to the code.
             * When we merge the content by cleaning up the section that $paragraphs is using
             * this is avoided.
             *
             * See wp-includes/formatting.php for where $paragraphs are split up.
             */
            $result = preg_replace(
                pattern: '/\n\s*\n/m',
                replacement: " ",
                subject: $address->content
            );
        } catch (Throwable $e) {
            try {
                Config::getLogger()->error(
                    message: new GetAddressException(
                        message: 'Failed to render get address widget.',
                        previous: $e
                    )
                );
            } catch (ConfigException) {
                $result = 'ResursBank: failed to render get address widget.';
            }
        }

        echo $result;
    }
}
