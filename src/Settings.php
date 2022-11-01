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
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use ResursBank\Module\Data;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Resurs Bank settings for WooCommerce.
 */
class Settings extends WC_Settings_Page
{
    /**
     * This prefix is used for various parts of the settings by WooCommerce,
     * for example, as an ID for these settings, and as a prefix for the values
     * in the database.
     */
    public const PREFIX = 'resursbank';

    /**
     * Initializes settings properties and registers WordPress actions for
     * rendering content and saving settings.
     */
    public function __construct()
    {
        $this->id = self::PREFIX;
        $this->label = 'Resurs Bank';

        // Adds the Resurs Bank tab.
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_page'], 20);

        // Renders the settings fields.
        add_action('woocommerce_settings_' . $this->id, [$this, 'output']);

        // Renders the sections (tabs) inside the Resurs Bank tab.
        add_action('woocommerce_sections_' . $this->id, [$this, 'output_sections']);

        // Saves settings to the database.
        add_action('woocommerce_settings_save_' . $this->id, [$this, 'save']);

        parent::__construct();
    }

    /**
     * Saves settings from our fields to the database.
     *
     * This method is called by WordPress actions registered in our constructor.
     *
     * @see self::__construct()
     * @return void
     */
    public function save(): void
    {
        global $current_section;

        woocommerce_update_options(
            $this->get_settings(section: $current_section)
        );
    }

    /**
     * Method is required by Woocommerce to render tab sections.
     *
     * @see parent::output_sections()
     * @return array - Parent returns mixed but documents array.
     */
    public function get_sections(): array
    {
        return [
            Api::SECTION_ID => Api::SECTION_TITLE,
            PaymentMethods::SECTION_ID => PaymentMethods::SECTION_TITLE,
            Advanced::SECTION_ID => Advanced::SECTION_TITLE,
        ];
    }

    /**
     * Outputs the HTML for the current tab section.
     *
     * @return void
     */
    public function output(): void
    {
        global $current_section;

        if ($current_section === '') {
            $current_section = 'api_settings';
        }

        if ($current_section === 'payment_methods') {
            // As WordPress requires html to be escaped at the echo, we do a late execute on this.
            try {
                if (StoreId::getData() !== '') {
                    echo Data::getEscapedHtml(content: PaymentMethods::getOutput(storeId: StoreId::getData()));
                } else {
                    // The lazy handler.
                    throw new Exception(
                        __('Please select a store in the API settings tab.')
                    );
                }
            } catch (Exception $e) {
                // @todo Add proper translation via ecom2.
                echo '<div style="border: 1px solid black !important; padding: 5px !important;">' . Data::getEscapedHtml(
                    $e->getMessage()
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
                $this->get_settings(section: empty($current_section) ? 'api_settings' : $current_section)
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
     */
    public function get_settings(string $section = ''): array
    {
        // Section must always be set, so if it is empty, this indicates that we're in the primary sub-tab!
        if ($section === '') {
            $section = 'api_settings';
        }

        $result = array_merge(
            Api::getSettings(),
            PaymentMethods::getSettings(),
            Advanced::getSettings(),
        );

        return $result[$section] ?? $result;
    }
}
