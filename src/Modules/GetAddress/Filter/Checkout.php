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
     * @todo Refactor, method is too big. WOO-895. Remove phpcs:ignore when done.
     */
    // phpcs:ignore
    public static function register(): void
    {
        add_filter(
            'woocommerce_before_checkout_form',
            static fn () => self::exec()
        );

        add_action(
            'wp_enqueue_scripts',
            static function (): void {
                wp_enqueue_script(
                    handle: 'rb-get-address',
                    src: Url::getScriptUrl(
                        module: 'GetAddress',
                        file: 'populateForm.js'
                    )
                );

                wp_enqueue_style(
                    handle: 'rb-get-address-style',
                    src: Url::getEcomUrl(
                        path: 'src/Module/Customer/Widget/get-address.css'
                    )
                );
            }
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

        echo $result;
    }
}
