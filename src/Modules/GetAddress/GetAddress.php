<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Customer\Widget\GetAddress as Widget;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Throwable;

/**
 * Implementation of get address widget in checkout.
 */
class GetAddress
{
    private static ?Widget $instance = null;

    /**
     * Register frontend related actions and filters.
     */
    public static function init(): void
    {
        add_action(
            hook_name: 'wp_enqueue_scripts',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\FrontendAssets::exec'
        );

        if (EnableGetAddress::isEnabled() === false) {
            return;
        }

        // Inject Get Address widget in blocked based checkout.
        add_filter(
            hook_name: 'the_content',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\Blocks\InjectFetchAddressWidget::exec'
        );

        // Inject Get Address widget in legacy checkout.
        add_filter(
            hook_name: 'woocommerce_before_checkout_form',
            callback: 'Resursbank\Woocommerce\Modules\GetAddress\Filter\Legacy\InjectFetchAddressWidget::exec'
        );
    }

    /**
     * Create and return Get Address Widget instance. We store it locally to
     * improve performance since we will need to call the widget from several
     * locations during the checkout page rendering.
     */
    public static function getWidget(): ?Widget
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        try {
            $getAddressUrl = Route::getUrl(route: Route::ROUTE_GET_ADDRESS);

            if ($getAddressUrl === '') {
                throw new IllegalValueException(
                    message: 'Failed to obtain get address widget URL.'
                );
            }

            self::$instance = new Widget(url: $getAddressUrl);
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        return self::$instance;
    }
}
