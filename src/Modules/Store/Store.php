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
            add_action(
                'admin_enqueue_scripts',
                'Resursbank\Woocommerce\Modules\Store\Store::onAdminEnqueueScripts'
            );
    }

    /**
     * Callback function because all of this needs to be done when an action runs, not just randomly called before
     * the relevant hooks are triggered (this causes crashing, including wp-admin becoming inaccessible).
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function onAdminEnqueueScripts(): void
    {
        if (
            !is_admin() ||
            !isset($_REQUEST['page']) ||
            !isset($_REQUEST['tab']) ||
            $_REQUEST['page'] !== 'wc-settings' ||
            $_REQUEST['tab'] !== 'resursbank' ||
            (
                isset($_REQUEST['section']) &&
                $_REQUEST['section'] !== 'api_settings'
            )
        ) {
            return;
        }

        try {
            $url = Route::getUrl(
                route: Route::ROUTE_GET_STORES_ADMIN,
                admin: true
            );

            $widget = new GetStores(
                storeSelectId: 'resursbank_store_id',
                environmentSelectId: 'resursbank_environment',
                clientIdInputId: 'resursbank_client_id',
                clientSecretInputId: 'resursbank_client_secret',
                spinnerClass: 'rb-store-fetching'
            );

            // All the below is required to render the inline CSS.
            wp_register_style('rb-store-admin-css', false);
            wp_enqueue_style('rb-store-admin-css');
            wp_add_inline_style(
                'rb-store-admin-css',
                '.rb-store-fetching select { background-image: url("' .
                get_admin_url() . '/images/loading.gif' . '") !important; }'
            );

            // All the below is required to render the inline JS.
            wp_register_script('rb-store-admin-scripts', false);
            wp_enqueue_script('rb-store-admin-scripts');
            wp_add_inline_script('rb-store-admin-scripts', $widget->content);
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
