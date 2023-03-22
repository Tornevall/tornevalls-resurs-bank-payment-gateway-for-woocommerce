<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Woocommerce\Settings\Filter\AddDocumentationLink;
use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

use function is_array;

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
     */
    public static function init(): void
    {
        // Render configuration page.
        add_action(
            hook_name: 'woocommerce_settings_page_init',
            callback: 'Resursbank\Woocommerce\Settings\Settings::renderSettingsPage'
        );

        Api::init();
        PartPayment::init();

        // Save changes to database.
        add_action(
            hook_name: 'woocommerce_settings_save_' . RESURSBANK_MODULE_PREFIX,
            callback: 'Resursbank\Woocommerce\Settings\Settings::saveSettings'
        );

        // Add link to Settings page from Plugin page in WP admin.
        add_filter(
            hook_name: 'plugin_action_links',
            callback: 'Resursbank\Woocommerce\Settings\Settings::addPluginActionLinks',
            priority: 10,
            accepted_args: 2
        );

        add_action(
            hook_name: 'woocommerce_admin_field_div_callback_management',
            callback: 'Resursbank\Woocommerce\Settings\Settings::renderManagementCallbackUrl',
            priority: 10,
            accepted_args: 2
        );

        add_action(
            hook_name: 'woocommerce_admin_field_div_callback_authorization',
            callback: 'Resursbank\Woocommerce\Settings\Settings::renderAuthorizationCallbackUrl',
            priority: 10,
            accepted_args: 2
        );

        AddDocumentationLink::register();
    }

    /**
     * Render displayable url for callbacks properly without stored values in database.
     *
     * @param array $settings
     * @throws IllegalValueException
     */
    public static function renderManagementCallbackUrl(array $settings): void
    {
        // Not using Sanitize::sanitizeHtml() here since our html should not be escaped.
        echo wp_kses(
            string: '<table class="form-table"><th scope="row">' . ($settings['title'] ?? '') . '</th><td>' .
            Url::getCallbackUrl(
                type: CallbackType::MANAGEMENT
            ) . '</td></table>',
            allowed_html: ['table' => ['class' => []], 'th' => ['scope' => []], 'td' => []]
        );
    }

    /**
     * Render displayable url for callbacks properly without stored values in database.
     *
     * @param array $settings
     * @throws IllegalValueException
     */
    public static function renderAuthorizationCallbackUrl(array $settings): void
    {
        // Not using Sanitize::sanitizeHtml() here since our html should not be escaped.
        echo wp_kses(
            string: '<table class="form-table"><th scope="row">' . ($settings['title'] ?? '') . '</th><td>' .
            Url::getCallbackUrl(
                type: CallbackType::AUTHORIZATION
            ) . '</td></table>',
            allowed_html: ['table' => ['class' => []], 'th' => ['scope' => []], 'td' => []]
        );
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
     */
    public static function saveSettings(): void
    {
        try {
            woocommerce_update_options(
                options: self::getSection(
                    section: self::getCurrentSectionId()
                )
            );
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
     * Resolve array of config options matching supplied section.
     */
    public static function getSection(
        string $section = Api::SECTION_ID
    ): array {
        $result = [];

        $data = match ($section) {
            Api::SECTION_ID => Api::getSettings(),
            Advanced::SECTION_ID => Advanced::getSettings(),
            PartPayment::SECTION_ID => PartPayment::getSettings(),
            OrderManagement::SECTION_ID => OrderManagement::getSettings()
        };

        if (isset($data[$section]) && is_array(value: $data[$section])) {
            $result = $data[$section];
        }

        return $result;
    }

    /**
     * Retrieve all settings as sequential array.
     */
    public static function getAll(): array
    {
        return array_merge(
            Api::getSettings(),
            Advanced::getSettings(),
            PartPayment::getSettings(),
            OrderManagement::getSettings()
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

        return $current_section === '' ? Api::SECTION_ID : $current_section;
    }

    /**
     * Add link to "Settings" page for our plugin in WP admin.
     */
    public static function addPluginActionLinks(
        mixed $links,
        mixed $file
    ): array {
        if (
            is_array(value: $links) &&
            $file === RESURSBANK_MODULE_DIR_NAME . '/init.php'
        ) {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                Route::getSettingsUrl(),
                Translator::translate(phraseId: 'settings')
            );
        }

        return $links;
    }
}
