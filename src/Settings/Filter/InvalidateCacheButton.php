<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings\Filter;

use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Filter (event listener) which adds custom button to invalidate cache store.
 */
class InvalidateCacheButton
{
    /**
     * Add event listener to render the custom button element.
     */
    public static function init(): void
    {
        add_action(
            'woocommerce_admin_field_rbinvalidatecachebutton',
            'Resursbank\Woocommerce\Settings\Filter\InvalidateCacheButton::render'
        );
    }

    /**
     * Callback function for rendering the button.
     */
    public static function render(): void
    {
        SettingsPage::renderButton(
            route: Route::ROUTE_ADMIN_CACHE_INVALIDATE,
            title: Translator::translate(phraseId: 'clear-cache'),
            error: Translator::translate(
                phraseId: 'failed-to-render-clear-cache-button'
            )
        );
    }
}
