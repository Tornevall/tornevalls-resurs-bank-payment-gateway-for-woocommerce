<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Settings\Filter\AddDocumentationLink;
use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

use const RESURSBANKABPAYMENTS_MODULE_PREFIX;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * General business logic for settings.
 *
 * NOTE: This is not part of Resursbank\Woocommerce\SettingsPage because that
 * class extends a WC class not available to us when we need to register events.
 */
class Settings
{
    /**
     * Setup event listeners to render our configuration page and save settings.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        /**
         * @noinspection PhpArgumentWithoutNamedIdentifierInspection
         */
        // Render configuration page.
        add_action(
            'woocommerce_settings_page_init',
            'Resursbank\Woocommerce\Settings\Settings::renderSettingsPage'
        );

        // Ensure About CSS is injected only on the About section.
        add_action(
            'in_admin_header',
            'Resursbank\\Woocommerce\\Settings\\Settings::maybeSetAboutCss'
        );

        /**
         * @noinspection PhpArgumentWithoutNamedIdentifierInspection
         */
        add_filter(
            'handle_bulk_actions-edit-shop_order',
            static function ($redirect, $action, $ids) {
                global $resursCheckBulkIds;

                foreach ($ids as $id) {
                    $resursCheckBulkIds[$id] = $action;
                }

                return $redirect;
            },
            10,
            3
        );

        Api::init();
        PartPayment::init();

        /**
         * @noinspection PhpArgumentWithoutNamedIdentifierInspection
         */
        // Save changes to database.
        add_action(
            'woocommerce_settings_save_' . RESURSBANKABPAYMENTS_MODULE_PREFIX,
            'Resursbank\Woocommerce\Settings\Settings::saveSettings'
        );

        /**
         * @noinspection PhpArgumentWithoutNamedIdentifierInspection
         */
        // Add a link to Settings page from Plugin page in WP admin.
        add_filter(
            'plugin_action_links',
            'Resursbank\Woocommerce\Settings\Settings::addPluginActionLinks',
            10,
            2
        );

        AddDocumentationLink::register();
    }

    /**
     * Callback method for rendering the settings page.
     */
    public static function renderSettingsPage(): void
    {
        new SettingsPage();
    }

    /**
     * Callback method that handles the saving of options.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function saveSettings(): void
    {
        try {
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            woocommerce_update_options(
                self::getSection(
                    section: self::getCurrentSectionId()
                )
            );

            Config::getCache()->invalidate();
            StoreRepository::getCache()->clear();
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: Translator::translate(
                    phraseId: 'save-settings-failed'
                )
            );
        }
    }

    /**
     * Resolve an array of config options matching supplied section.
     *
     * @throws ConfigException
     */
    public static function getSection(
        string $section = Api::SECTION_ID
    ): array {
        $result = [];

        $data = match ($section) {
            Api::SECTION_ID => Api::getSettings(),
            Advanced::SECTION_ID => Advanced::getSettings(),
            PartPayment::SECTION_ID => PartPayment::getSettings(),
            OrderManagement::SECTION_ID => OrderManagement::getSettings(),
            Callback::SECTION_ID => Callback::getSettings()
        };

        if (isset($data[$section]) && is_array(value: $data[$section])) {
            $result = $data[$section];
        }

        return $result;
    }

    /**
     * Retrieve all settings as sequential array.
     *
     * @throws ConfigException
     */
    public static function getAll(): array
    {
        return array_merge(
            Api::getSettings(),
            Advanced::getSettings(),
            PartPayment::getSettings(),
            OrderManagement::getSettings(),
            Callback::getSettings()
        );
    }

    /**
     * Return currently selected config section for Resurs Bank tab, fallback
     * to API Settings section.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public static function getCurrentSectionId(): string
    {
        global $current_section;

        if (!is_string(value: $current_section) || $current_section === '') {
            return Api::SECTION_ID;
        }

        return $current_section;
    }

    /**
     * Add link to "Settings" page for our plugin in WP admin.
     *
     * @noinspection HtmlUnknownTarget
     */
    public static function addPluginActionLinks(
        mixed $links,
        mixed $file
    ): array {
        if (
            is_array(value: $links) &&
            $file === RESURSBANKABPAYMENTS_MODULE_DIR_NAME . '/init.php'
        ) {
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                Route::getSettingsUrl(),
                Translator::translate(phraseId: 'settings')
            );
        }

        return $links;
    }

    /**
     * Inject About tab CSS only when the About section is active.
     */
    public static function maybeSetAboutCss(): void
    {
        if (self::getCurrentSectionId() !== About::SECTION_ID) {
            return;
        }

        About::setCss();
    }
}
