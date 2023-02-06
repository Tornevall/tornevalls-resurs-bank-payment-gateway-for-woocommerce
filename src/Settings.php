<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use RuntimeException;
use Throwable;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Render Resurs Bank settings page for WooCommerce.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class Settings extends WC_Settings_Page
{
    /**
     * Create a custom tab for our configuration page within the WC
     * configuration.
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
     * @throws ConfigException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
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
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function output(): void
    {
        global $current_section;

        match ($current_section) {
            'payment_methods' => $this->renderPaymentMethodsPage(),
            default => $this->renderSettingsPage(section: $current_section)
        };
    }

    /**
     * Render content of any setting tab for our config page.
     *
     * @throws ConfigException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     */
    public function renderSettingsPage(string $section): void
    {
        // Echo table element to get Woocommerce to properly render our
        // settings within the right elements and styling. If you include
        // PHTML templates within the table, it's possible their HTML could
        // be altered by Woocommerce.
        echo '<table class="form-table">';

        // Always default to first "tab" if no section has been selected.
        WC_Admin_Settings::output_fields(
            options: $this->get_settings(section: (
                $section === '' ? 'api_settings' : $section
            ))
        );

        echo '</table>';
    }

    /**
     * Render content of the payment method tab for our config page.
     *
     * @todo Translate error message WOO-1010
     */
    public function renderPaymentMethodsPage(): void
    {
        try {
            if (StoreId::getData() === '') {
                throw new RuntimeException(
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
            echo Data::getEscapedHtml(content:
                '<div style="border: 1px solid #590804; padding: 5px; color: #fff; background: #8a110a;">' .
                'Failed to render payment methods. Please review logs for more information.' .
                '<br />' .
                '<b>Exception</b>' .
                '<br />' .
                $e->getMessage() .
                '</div>');
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
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @noinspection PhpMissingParentCallCommonInspection
     * @todo Refactor this WOO-1009
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
