<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Woocommerce\Settings\About;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\Callback;
use Resursbank\Woocommerce\Settings\OrderManagement;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use Resursbank\Woocommerce\Settings\Settings;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Render Resurs Bank settings page for WooCommerce.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class SettingsPage extends WC_Settings_Page
{
    /**
     * Create a custom tab for our configuration page within the WC
     * configuration.
     */
    public function __construct()
    {
        $this->id = RESURSBANK_MODULE_PREFIX;
        $this->label = 'Resurs Bank';

        parent::__construct();
    }

    /**
     * Method is required by Woocommerce to render tab sections.
     *
     * NOTE: Suppressing PHPCS because we cannot name method properly (parent).
     *
     * @phpcsSuppress
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_sections(): array // phpcs:ignore
    {
        // New sections should preferably be placed before the advanced section.
        return [
            Api::SECTION_ID => Api::getTitle(),
            PaymentMethods::SECTION_ID => Translator::translate(phraseId: 'payment-methods'),
            PartPayment::SECTION_ID => PartPayment::getTitle(),
            OrderManagement::SECTION_ID => OrderManagement::getTitle(),
            Callback::SECTION_ID => Callback::getTitle(),
            Advanced::SECTION_ID => Advanced::getTitle(),
            About::SECTION_ID => About::getTitle(),
        ];
    }

    /**
     * NOTE: Suppressing PHPCS because we cannot name method properly (parent).
     *
     * @inheritdoc
     * @throws ConfigException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_settings(): array // phpcs:ignore
    {
        return array_merge(array_values(array: Settings::getAll()));
    }

    /**
     * Outputs the HTML for the current tab section.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function output(): void
    {
        try {
            $section = Settings::getCurrentSectionId();

            if ($section === PaymentMethods::SECTION_ID) {
                PaymentMethods::render();
            } elseif ($section === About::SECTION_ID) {
                About::render();
            } elseif ($section === Callback::SECTION_ID) {
                Callback::render();
            } else {
                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields(
                    options: Settings::getSection(section: $section)
                );
                echo '</table>';
            }
        } catch (Throwable $error) {
            Logger::error(message: $error);

            // Display a generic error message to the user. Letting them know
            // the page could not render and info is logged.
            echo '<div class="error"><p>' .
                Translator::translate(phraseId: 'content-render-failed') .
            '</p></div>';
        }
    }
}
