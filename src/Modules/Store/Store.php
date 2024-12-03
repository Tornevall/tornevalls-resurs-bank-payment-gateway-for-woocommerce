<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Store;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Store\Widget\GetStores;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
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
     * Callback function because all of these needs to be done when an action runs, not just randomly called before
     * the relevant hooks are triggered (this causes crashing, including wp-admin becoming inaccessible).
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function onAdminEnqueueScripts(): void
    {
        if (!self::isOnResursBankSettingsPage()) {
            return;
        }

        try {
            self::enqueueStyles();
            self::enqueueScripts();
            self::localizeScripts();
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: Translator::translate(
                    phraseId: 'failed-initializing-store-selector-assistant'
                )
            );
        }
    }

    /**
     * Initialize the GetStores widget.
     */
    private static function initializeWidget(): GetStores
    {
        return new GetStores(
            automatic: false,
            storeSelectId: 'resursbank_store_id',
            environmentSelectId: 'resursbank_environment',
            clientIdInputId: 'resursbank_client_id',
            clientSecretInputId: 'resursbank_client_secret',
            spinnerClass: 'rb-store-fetching'
        );
    }

    /**
     * Checks if we are on the WooCommerce settings page and the Resurs Bank tab.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function isOnResursBankSettingsPage(): bool
    {
        return is_admin() &&
            isset($_REQUEST['page'], $_REQUEST['tab']) &&
            $_REQUEST['page'] === 'wc-settings' &&
            $_REQUEST['tab'] === 'resursbank' &&
            (!isset($_REQUEST['section']) || $_REQUEST['section'] === 'api_settings');
    }

    /**
     * Required to render the inline CSS.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function enqueueStyles(): void
    {
        wp_register_style('rb-store-admin-css', false);
        wp_enqueue_style('rb-store-admin-css');
        wp_add_inline_style(
            'rb-store-admin-css',
            '.rb-store-fetching select { background-image: url("' .
            get_admin_url() . '/images/loading.gif' . '") !important; }'
        );
    }

    /**
     * Required to render the inline JS.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function enqueueScripts(): void
    {
        $widget = self::initializeWidget();

        wp_register_script('rb-store-admin-scripts', false);
        wp_enqueue_script('rb-store-admin-scripts');
        wp_add_inline_script('rb-store-admin-scripts', $widget->content);

        wp_register_script(
            'rb-store-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Store',
                file: 'rb-store.js'
            )
        );

        wp_enqueue_script(
            'rb-store-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Store',
                file: 'rb-store.js'
            ),
            ['jquery']
        );
    }

    /**
     * Localization handler.
     *
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function localizeScripts(): void
    {
        try {
            $fetchStoresString = Translator::translate(
                phraseId: 'fetch-stores'
            );
            $noFetchUrl = Translator::translate(
                phraseId: 'get-stores-missing-fetch-url'
            );
        } catch (Throwable) {
            $fetchStoresString = 'Fetch Stores';
            $noFetchUrl = 'Failed to obtain fetch URL.';
        }

        wp_localize_script(
            'rb-store-admin-scripts-load',
            'rbStoreAdminLocalize',
            [
                'url' => Route::getUrl(
                    route: Route::ROUTE_GET_STORES_ADMIN
                ),
                'fetch_stores_translation' => $fetchStoresString,
                'no_fetch_url' => $noFetchUrl
            ]
        );
    }
}
