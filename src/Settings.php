<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Translator;
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use Throwable;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Resurs Bank settings for WooCommerce.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class Settings extends WC_Settings_Page
{
    /**
     * Initializes settings properties and registers WordPress actions for
     * rendering content and saving settings.
     */
    public function __construct()
    {
        $this->id = RESURSBANK_MODULE_PREFIX;
        $this->label = 'Resurs Bank';

        // Adds the Resurs Bank tab.
        add_filter(
            hook_name: 'woocommerce_settings_tabs_array',
            callback: [$this, 'add_settings_page'],
            priority: 20
        );

        // Renders the settings fields.
        add_action(
            hook_name: 'woocommerce_settings_' . $this->id,
            callback: [$this, 'output']
        );

        // Renders the sections (tabs) inside the Resurs Bank tab.
        add_action(
            hook_name: 'woocommerce_sections_' . $this->id,
            callback: [$this, 'output_sections']
        );

        // Saves settings to the database.
        add_action(
            hook_name: 'woocommerce_settings_save_' . $this->id,
            callback: [$this, 'save']
        );

        parent::__construct();
    }

    /**
     * Saves settings from our fields to the database.
     *
     * This method is called by WordPress actions registered in our constructor.
     *
     * @see self::__construct()
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function save(): void
    {
        global $current_section;

        woocommerce_update_options(
            options: $this->get_settings(section: $current_section)
        );
    }

    /**
     * Method is required by Woocommerce to render tab sections.
     *
     * @return array - Parent returns mixed but documents array.
     * @throws ConfigException
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws IllegalTypeException
     * @see parent::output_sections()
     * @phpcsSuppress
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_sections(): array // phpcs:ignore
    {
        return [
            Api::SECTION_ID => Api::getTitle(),
            PaymentMethods::SECTION_ID => PaymentMethods::SECTION_TITLE,
            Advanced::SECTION_ID => Advanced::SECTION_TITLE,
            PartPayment::SECTION_ID => PartPayment::SECTION_TITLE,
        ];
    }

    /**
     * Outputs the HTML for the current tab section.
     *
     * @throws ConfigException
     * @phpcsSuppress
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @noinspection PhpMissingParentCallCommonInspection
     * @todo Refactor this. WOO-873. Remove suppression after refactor, remember phpcs:ignore below.
     */
    // phpcs:ignore
    public function output(): void
    {
        global $current_section;

        if ($current_section === '') {
            $current_section = 'api_settings';
        }

        if ($current_section === 'payment_methods') {
            // As WordPress requires html to be escaped at the echo, we do a late execute on this.
            try {
                if (StoreId::getData() === '') {
                    // The lazy handler.
                    throw new Exception(
                        message: Translator::translate(
                            phraseId: 'please-select-a-store'
                        )
                    );
                }

                echo Data::getEscapedHtml(
                    content: PaymentMethods::getOutput(
                        storeId: StoreId::getData()
                    )
                );
            } catch (Throwable $e) {
                Config::getLogger()->error(
                    message: 'Failed to render payment methods: ' . $e->getMessage()
                );
                // @todo Add proper translation via ecom2.
                echo '<div style="border: 1px solid black !important; padding: 5px !important;">
                    Failed to render payment methods:  ' .
                    Data::getEscapedHtml(
                        content: $e->getMessage()
                    ) . '</div>';
            }
        } else {
            // Echo table element to get Woocommerce to properly render our
            // settings within the right elements and styling. If you include
            // PHTML templates within the table, it's possible their HTML could
            // be altered by Woocommerce.
            echo '<table class="form-table">';

            // Always default to first "tab" if no section has been selected.
            WC_Admin_Settings::output_fields(
                options: $this->get_settings(section: (
                    empty($current_section) ? 'api_settings' : $current_section
                ))
            );

            echo '</table>';
        }
    }

    /**
     * Return a list of setting fields. The fields are in a format that
     * WooCommerce can parse and render.
     *
     * @param string $section - If you specify a section, then the list will
     * consist of fields from only that section. An empty string will return
     * all fields from all sections.
     * @return array
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_settings(string $section = ''): array // phpcs:ignore
    {
        // Section must always be set, so if it is empty, this indicates that we're in the primary sub-tab!
        if ($section === '') {
            $section = 'api_settings';
        }

        $result = array_merge(
            Api::getSettings(),
            PaymentMethods::getSettings(),
            Advanced::getSettings(),
            PartPayment::getSettings()
        );

        return $result[$section] ?? $result;
    }
}
