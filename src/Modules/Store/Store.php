<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Store;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Widget\GetStores\Js as GetStores;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WordPress;
use Throwable;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

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
            spinnerClass: 'resursbankabpaygw-store-fetching'
        );
    }

    /**
     * Checks if we are on the WooCommerce settings page and the Resurs Bank tab.
     */
    private static function isOnResursBankSettingsPage(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = WordPress::getQueryParam('page');
        $tab = WordPress::getQueryParam('tab');
        $section = WordPress::getQueryParam('section');

        return $page === 'wc-settings' &&
            $tab === 'resursbank' &&
            ($section === '' || $section === 'api_settings');
    }

    /**
     * Required to render the inline CSS.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function enqueueStyles(): void
    {
        wp_register_style(
            'resursbankabpaygw-store-admin-css',
            false,
            [],
            UserAgent::getPluginVersion()
        );
        wp_enqueue_style('resursbankabpaygw-store-admin-css');
        wp_add_inline_style(
            'resursbankabpaygw-store-admin-css',
            '.resursbankabpaygw-store-fetching select { background-image: url("' .
            esc_url(
                get_admin_url() . '/images/loading.gif'
            ) . '") !important; }'
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

        wp_register_script(
            'resursbankabpaygw-store-admin-scripts',
            false,
            [],
            UserAgent::getPluginVersion(),
            true
        );
        wp_enqueue_script('resursbankabpaygw-store-admin-scripts');
        wp_add_inline_script('resursbankabpaygw-store-admin-scripts', $widget->content);

        wp_register_script(
            'resursbankabpaygw-store-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Store',
                file: 'rb-store.js'
            ),
            ['jquery'],
            UserAgent::getPluginVersion(),
            true
        );

        wp_enqueue_script(
            'resursbankabpaygw-store-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Store',
                file: 'rb-store.js'
            ),
            ['jquery'],
            UserAgent::getPluginVersion(),
            true
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
            'resursbankabpaygw-store-admin-scripts-load',
            'resursbankabpaygwStoreAdminLocalize',
            [
                'url' => Route::getUrl(
                    route: Route::ROUTE_GET_STORES_ADMIN
                ),
                'fetch_stores_translation' => $fetchStoresString,
                'no_fetch_url' => $noFetchUrl,
                'nonce' => wp_create_nonce('resursbank_get_stores_admin')
            ]
        );
    }
}
