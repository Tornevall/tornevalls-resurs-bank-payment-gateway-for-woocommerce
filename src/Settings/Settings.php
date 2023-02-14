<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
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
    public static function register(): void
    {
        // Render configuration page.
        add_action(
            hook_name: 'woocommerce_settings_page_init',
            callback: static function (): void {
                new SettingsPage();
            }
        );

        // Save changes to database.
        add_action(
            hook_name: 'woocommerce_settings_save_' . RESURSBANK_MODULE_PREFIX,
            callback: static function (): void {
                try {
                    woocommerce_update_options(
                        options: self::getSection(
                            section: self::getCurrentSectionId()
                        )
                    );
                } catch (Throwable $e) {
                    Log::error(
                        error: $e,
                        msg: Translator::translate(
                            phraseId: 'save-settings-failed'
                        )
                    );
                }
            }
        );
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
            PartPayment::SECTION_ID => PartPayment::getSettings()
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
            PartPayment::getSettings()
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
}