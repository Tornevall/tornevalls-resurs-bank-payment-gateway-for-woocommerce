<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Module\Customer\Widget\GetAddress;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Render get address form above the form on the checkout page.
 */
class Checkout
{
    /**
     * Register filter subscribers
     */
    public static function register(): void
    {
        add_filter(
            hook_name: 'woocommerce_before_checkout_form',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout::exec'
        );

        add_action(
            hook_name: 'wp_enqueue_scripts',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout::loadScripts'
        );
    }

    /**
     * Loads script and stylesheet for form.
     */
    public static function loadScripts(): void
    {
        wp_enqueue_script(
            handle: 'rb-get-address',
            src: Url::getScriptUrl(
                module: 'GetAddress',
                file: 'populateForm.js'
            ),
            deps: ['rb-set-customertype']
        );

        wp_enqueue_style(
            handle: 'rb-get-address-style',
            src: Url::getEcomUrl(
                path: 'src/Module/Customer/Widget/get-address.css'
            )
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
                fetchUrl: Route::getUrl(route: Route::ROUTE_GET_ADDRESS)
            );

            $result = $address->content;
        } catch (Throwable $e) {
            try {
                Config::getLogger()->error(
                    message: new GetAddressException(
                        message: 'Failed to render get address widget.',
                        previous: $e
                    )
                );
            } catch (ConfigException) {
                $result = 'Resursbank: failed to render get address widget.';
            }
        }

        // @todo Reinstate sanitizer (see WOO-954 ECP-327).
//        echo Data::getEscapedHtml($result);
        echo $result;
    }
}
