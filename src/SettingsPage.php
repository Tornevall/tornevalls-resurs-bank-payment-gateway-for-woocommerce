<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce;

use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Settings\About;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;
use Resursbank\Woocommerce\Settings\Callback;
use Resursbank\Woocommerce\Settings\OrderManagement;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Settings\PaymentMethods;
use Resursbank\Woocommerce\Settings\Settings;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
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
            PaymentMethods::SECTION_ID => PaymentMethods::getTitle(),
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
        $section = Settings::getCurrentSectionId();

        if ($section === 'payment_methods') {
            $this->renderPaymentMethodsPage();
            return;
        }

        if ($section === 'about') {
            $this->renderAboutPage();
            return;
        }

        $this->renderSettingsPage(section: $section);
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

    /**
     * Render content of the payment method tab for our config page.
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

            echo PaymentMethods::getOutput(storeId: StoreId::getData());
        } catch (Throwable $e) {
            Log::error(error: $e, message: $e->getMessage());

            $this->renderError(view: 'payment_methods');
        }
    }

    /**
     * Render Support Info tab.
     */
    public function renderAboutPage(): void
    {
        try {
            echo About::getWidgetHtml();
        } catch (Throwable $error) {
            Log::error(error: $error);

            $this->renderError();
        }
    }

    /**
     * Render an error message (cannot use the message bag since that has
     * already been rendered).
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    /**
     * Render an error message based on the view and exception provided.
     *
     * @param string $view The view context for the error (e.g., 'payment_methods', 'advanced').
     * @param Throwable|null $throwable Optional exception for additional error context.
     */
    private function renderError(string $view = '', ?Throwable $throwable = null): void
    {
        $additional = $this->getAdditionalMessage(view: $view);
        $msg = $this->getErrorMessage(view: $view, throwable: $throwable);
        Admin::getAdminErrorNote(message: $msg, additional: $additional);
    }

    /**
     * Get additional message for the error note based on the view context.
     *
     * @param string $view The view context for the error.
     * @return string The additional context message for the error note.
     */
    private function getAdditionalMessage(string $view): string
    {
        if ($view === 'payment_methods') {
            // Suggest configuring credentials if they are missing
            if (!Connection::hasCredentials()) {
                return '<b>' . Translator::translate(
                    phraseId: 'configure-credentials'
                ) . '</b>';
            }

            // Suggest configuring the store if store data is missing
            if (StoreId::getData() === '') {
                return Translator::translate(phraseId: 'configure-store');
            }
        }

        // Default additional message for other views
        return Translator::translate(phraseId: 'see-log');
    }

    /**
     * Get the main error message based on the view context and optional exception.
     *
     * @param string $view The view context for the error.
     * @param Throwable|null $throwable Optional exception for additional error details.
     * @return string The main error message to display.
     */
    private function getErrorMessage(string $view, ?Throwable $throwable = null): string
    {
        if ($view === 'payment_methods') {
            // Specific error message for payment methods rendering failure
            return Translator::translate(
                phraseId: 'payment-methods-widget-render-failed'
            );
        }

        if ($view === 'advanced') {
            // Error message for advanced view with more exception details. Getting errors on this view
            // is never good, since error logging are actually configured here, so we need to show more of them.
            $msg = Translator::translate(phraseId: 'content-render-failed');

            if ($throwable instanceof Throwable) {
                $msg .= '<br><b>' . $throwable->getMessage() . '</b>';
            }

            return $msg;
        }

        // Default error message for other views
        return Translator::translate(phraseId: 'content-render-failed');
    }
}
