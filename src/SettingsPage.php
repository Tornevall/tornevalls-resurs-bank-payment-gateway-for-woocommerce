<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\Widget\PaymentMethod\Html as PaymentMethodWidget;
use Resursbank\Ecom\Module\Widget\SupportInfo\Html as EcomSupportInfo;
use Resursbank\Woocommerce\Settings\About;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\Callback;
use Resursbank\Woocommerce\Settings\OrderManagement;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\Settings;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\UserAgent;
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
     * Callback function for rendering custom button element.
     */
    public static function renderButton(
        string $route,
        string $title,
        string $error
    ): void {
        $element = '<div class="error notice" style="padding: 10px;">' . $error . '</div>';

        try {
            $element = '<a class="button-primary" href="' .
                Route::getUrl(route: $route, admin: true) .
                '">' . $title . '</a>';
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        echo <<<EX
<tr>
  <th scope="row" class="titledesc" />
  <td class="forminp">
    $element
  </td>
</tr>
EX;
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
            'payment_methods' => Translator::translate(phraseId: 'payment-methods'),
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

            if ($section === 'payment_methods') {
                $GLOBALS['hide_save_button'] = '1';

                echo (new PaymentMethodWidget(
                    paymentMethods: PaymentMethodRepository::getPaymentMethods()
                ))->content;

                return;
            }

            if ($section === 'about') {
                $GLOBALS['hide_save_button'] = '1';

               echo (new EcomSupportInfo(
                    minimumPhpVersion: '8.1',
                    maximumPhpVersion: '8.4',
                    pluginVersion: UserAgent::getPluginVersion()
                ))->content;

                return;
            }

            $this->renderSettingsPage(section: $section);
        } catch (Throwable $e) {
            Log::error(error: $e);

            // Add visual note stating the page failed to render.
            Admin::getAdminErrorNote(message: Translator::translate(phraseId: 'content-render-failed'));
        }
    }

    /**
     * Render content of any setting tab for our config page.
     */
    public function renderSettingsPage(string $section): void
    {
        // Echo table element to get Woocommerce to properly render our
        // settings within the right elements and styling. If you include
        // PHTML templates within the table, it's possible their HTML could
        // be altered by Woocommerce.
        echo '<table class="form-table">';

        // Always default to first "tab" if no section has been selected.
        try {
            WC_Admin_Settings::output_fields(
                options: Settings::getSection(section: $section)
            );
        } catch (Throwable $e) {
            Log::error(error: $e);

            $this->renderError(view: $section, throwable: $e);
        }

        echo '</table>';
    }
}
