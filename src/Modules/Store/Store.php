<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Store;

use Resursbank\Ecom\Module\Store\Widget\GetStores;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Store related business logic.
 */
class Store
{
    /**
     * Render JavaScript widget that will update the select element containing
     * available stores as API credentials are modified.
     */
    public static function initAdmin(): void
    {
        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $url = Route::getUrl(
                route: Route::ROUTE_GET_STORES_ADMIN,
                admin: true
            );

            $widget = new GetStores(
                fetchUrl: $url,
                environmentSelectId: 'resursbank_environment',
                clientIdInputId: 'resursbank_client_id',
                clientSecretInputId: 'resursbank_client_secret',
                storeSelectId: 'resursbank_store_id',
                spinnerClass: 'rb-store-fetching'
            );

            // All the below is required to render the inline CSS.
            wp_register_style(handle: 'rb-store-admin-css', src: '');
            wp_enqueue_style(handle: 'rb-store-admin-css');
            wp_add_inline_style(
                handle: 'rb-store-admin-css',
                data: '.rb-store-fetching select { background-image: url("' .
                    get_admin_url() . '/images/loading.gif' . '") !important; }'
            );

            // All the below is required to render the inline JS.
            wp_register_script(handle: 'rb-store-admin-scripts', src: '');
            wp_enqueue_script(handle: 'rb-store-admin-scripts');
            wp_add_inline_script(
                handle: 'rb-store-admin-scripts',
                data: $widget->content
            );
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: Translator::translate(
                    phraseId: 'failed-initializing-store-selector-assistant'
                )
            );
        }
    }
}
