<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Location;
use Resursbank\Ecom\Lib\Model\PaymentMethod as EcomPaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Widget\PartPayment\Html as EcomPartPayment;
use Resursbank\Ecom\Module\Widget\PartPayment\Js as EcomPartPaymentJs;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled as PartPaymentOptions;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Util\HtmlSanitizer;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Product;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Part payment widget
 */
class PartPayment
{
    /**
     * ECom Part Payment widget instance.
     */
    private static ?EcomPaymentMethod $paymentMethod = null;

    /**
     * Init method for frontend scripts and styling.
     *
     * NOTE: Cannot place isEnabled() check here to prevent hooks, product not
     * available yet.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initFrontend(): void
    {
        if (!PartPaymentOptions::isEnabled()) {
            return;
        }

        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\PartPayment\PartPayment::setJs'
        );
        add_action(
            'woocommerce_single_product_summary',
            'Resursbank\Woocommerce\Modules\PartPayment\PartPayment::renderWidget'
        );
    }

    /**
     * Init method for admin script.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initAdmin(): void
    {
        add_action(
            'admin_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\PartPayment\Admin::setJs'
        );
    }

    /**
     * Output widget HTML if on a single product page.
     *
     * NOTE:
     * - The HTML comes from an SDK.
     * - wp_kses() is fine, but you must allow the specific inline CSS properties used by the SDK.
     *   Otherwise, "style=\"display: none;\"" can be stripped and loader/overlay/error become visible.
     *
     * @throws ConfigException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function renderWidget(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        Config::setLocation(
            location: Location::from(value: WooCommerce::getStoreCountry())
        );

        try {
            $widgetHtml = (string) (new EcomPartPayment(
                paymentMethod: self::getPaymentMethod(),
                months: (int) Period::getData(),
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                displayInfoText: self::displayInfoText(),
                threshold: Limit::getData()
            ))->content;

            // Normalize SDK HTML where wp_kses commonly breaks visuals.
            // Some warning SVGs rely on <style>.st0{fill:#AA1E1E;}</style>, which is typically stripped.
            // We remove the <style> tag and inline the fill attribute instead.
            $widgetHtml = self::normalizeWidgetHtml($widgetHtml);

            // Allow only the minimal CSS properties needed by the SDK markup.
            // Applied only for this rendering, then removed.
            $styleFilter = static function (array $styles): array {
                // Common for loaders/overlays in SDK markup.
                $styles[] = 'display';
                $styles[] = 'visibility';
                $styles[] = 'opacity';

                // If the SDK outputs inline SVG path styles.
                $styles[] = 'fill';
                $styles[] = 'fill-opacity';
                $styles[] = 'fill-rule';
                $styles[] = 'stroke';
                $styles[] = 'stroke-width';
                $styles[] = 'stroke-miterlimit';

                return array_values(array_unique($styles));
            };

            add_filter('safe_style_css', $styleFilter);

            echo '<div id="rb-pp-widget-container">' .
                wp_kses(
                    $widgetHtml,
                    self::getWidgetAllowlist()
                ) .
                '</div>';

            remove_filter('safe_style_css', $styleFilter);
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Set Js if on single product page.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function setJs(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            $widgetJsSafe = (string) (new EcomPartPaymentJs(
                paymentMethod: self::getPaymentMethod(),
                months: (int) Period::getData(),
                amount: self::getPriceData(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                threshold: Limit::getData()
            ))->content;

            wp_enqueue_script(
                'resursbankabpaygw-partpayment-script',
                Url::getResourceUrl(
                    module: 'PartPayment',
                    file: 'part-payment.js'
                ),
                ['jquery'],
                UserAgent::getPluginVersion(),
                true
            );

            if ($widgetJsSafe !== '') {
                /*
                 * Trusted SDK payload.
                 * This content is generated by the Resurs Bank SDK and must remain executable JS.
                 * It cannot be passed through esc_js() or wp_kses() without breaking the script.
                 * Per WordPress escaping guidance, this is treated as a safe prebuilt payload.
                 */
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prebuilt safe JS payload from trusted SDK, escaped at source by contract.
                wp_add_inline_script(
                    'resursbankabpaygw-partpayment-script',
                    $widgetJsSafe
                );
            }

            wp_localize_script(
                'resursbankabpaygw-partpayment-script',
                'resursbankabpaygwPartPaymentScript',
                [
                    'product_price' => self::getPriceData(),
                ]
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    public static function getPaymentMethod(): ?EcomPaymentMethod
    {
        if (self::$paymentMethod !== null) {
            return self::$paymentMethod;
        }

        try {
            $paymentMethodSet = PaymentMethod::getData();

            if ($paymentMethodSet === '') {
                throw new EmptyValueException(
                    message: 'Payment method is not properly configured. Part payment view can not be used.'
                );
            }

            self::$paymentMethod = Repository::getById(
                paymentMethodId: $paymentMethodSet
            );

            if (self::$paymentMethod === null) {
                throw new IllegalTypeException(
                    message: "Payment method $paymentMethodSet not found."
                );
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return self::$paymentMethod;
    }

    /**
     * Indicates whether widget should be visible or not.
     */
    public static function isEnabled(): bool
    {
        try {
            $amount = self::getPriceData();
            $method = self::getPaymentMethod();

            // Enabled if there is a product and a price.
            return PartPaymentOptions::isEnabled() &&
                PaymentMethod::getData() !== '' &&
                is_product() &&
                $amount > 0.0 &&
                $amount >= $method->minPurchaseLimit &&
                $amount <= $method->maxPurchaseLimit;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return false;
    }

    /**
     * Get checkout or product price, with optional override via filter.
     */
    private static function getPriceData(): float
    {
        try {
            $priceData = is_checkout()
                ? WooCommerce::getCartTotals()
                : (float) self::getProduct()?->get_price();

            // Let partners override.
            $priceDataMaybe = (float) apply_filters(
                'resursbank_pp_price_data',
                $priceData,
                self::getProduct()
            );

            // Only accept positive values from filter.
            if ($priceDataMaybe > 0.0) {
                $priceData = $priceDataMaybe;
            }
        } catch (Throwable) {
            $priceData = WooCommerce::getCartTotals();
        }

        return $priceData;
    }

    /**
     * Programmatically control whether part payment info text should be shown or hidden. Default is to show.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function displayInfoText(): bool
    {
        $returnBool = apply_filters(
            'resursbank_display_part_payment_info_text',
            true
        );
        return is_bool(value: $returnBool) ? $returnBool : false;
    }

    /**
     * @throws IllegalTypeException
     */
    private static function getProduct(): WC_Product
    {
        $product = wc_get_product(get_the_ID());

        if (!$product instanceof WC_Product) {
            $product = wc_get_product();
        }

        if (!$product instanceof WC_Product) {
            throw new IllegalTypeException(message: 'Unable to fetch product');
        }

        return $product;
    }

    /**
     * Allowlist for SDK-generated widget markup.
     *
     * This wraps HtmlSanitizer::getPartPaymentWidgetAllowlist() and patches the parts that commonly break:
     * - SVG attributes used by spinners/logos.
     * - viewBox normalization (WordPress may lowercase attributes).
     */
    private static function getWidgetAllowlist(): array
    {
        $allowed = HtmlSanitizer::getPartPaymentWidgetAllowlist();

        // The SDK "Read more" component relies on:
        // - data-* attributes (e.g. data-payment-method)
        // - stable ids (e.g. rb-rm-model-<id>)
        // - an iframe that is shown/hidden by JS
        // If any of these are removed by kses, the click handler will appear to "do nothing".

        // Ensure div supports ids, data-* and inline style used for display toggling.
        if (!isset($allowed['div']) || !is_array($allowed['div'])) {
            $allowed['div'] = [];
        }

        $allowed['div']['class'] = true;
        $allowed['div']['id'] = true;
        $allowed['div']['data-*'] = true;
        $allowed['div']['style'] = true;

        // Ensure iframe survives (used by the modal).
        if (!isset($allowed['iframe']) || !is_array($allowed['iframe'])) {
            $allowed['iframe'] = [];
        }

        $allowed['iframe']['class'] = true;
        $allowed['iframe']['src'] = true;
        $allowed['iframe']['loading'] = true;
        $allowed['iframe']['title'] = true;
        $allowed['iframe']['allow'] = true;
        $allowed['iframe']['referrerpolicy'] = true;

        // Links inside the widget (e.g. Konsumentverket link).
        if (!isset($allowed['a']) || !is_array($allowed['a'])) {
            $allowed['a'] = [];
        }

        $allowed['a']['href'] = true;
        $allowed['a']['title'] = true;
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;

        // Common text/layout tags used by the SDK markup.
        $allowed['p'] ??= [];
        $allowed['p']['class'] = true;
        $allowed['p']['id'] = true;
        $allowed['p']['style'] = true;

        $allowed['strong'] ??= [];
        $allowed['strong']['class'] = true;
        $allowed['strong']['id'] = true;
        $allowed['strong']['style'] = true;

        $allowed['aside'] ??= [];
        $allowed['aside']['class'] = true;
        $allowed['aside']['id'] = true;
        $allowed['aside']['style'] = true;

        // SVG support (spinner + logo + warning icon).
        // If these tags are not allowed, wp_kses will leave an empty <svg></svg> (logo disappears).
        $allowed['defs'] ??= [];
        $allowed['defs']['id'] = true;

        $allowed['g'] ??= [];
        $allowed['g']['id'] = true;
        $allowed['g']['transform'] = true;

        if (!isset($allowed['svg']) || !is_array($allowed['svg'])) {
            $allowed['svg'] = [];
        }

        $allowed['svg']['class'] = true;
        $allowed['svg']['width'] = true;
        $allowed['svg']['height'] = true;

        // WordPress may normalize attribute names to lowercase.
        $allowed['svg']['viewBox'] = true;
        $allowed['svg']['viewbox'] = true;

        $allowed['svg']['version'] = true;
        $allowed['svg']['id'] = true;
        $allowed['svg']['xmlns'] = true;
        $allowed['svg']['xmlns:svg'] = true;
        $allowed['svg']['xmlns:xlink'] = true;
        $allowed['svg']['xml:space'] = true;

        // Accessibility attributes often present in SVG.
        $allowed['svg']['role'] = true;
        $allowed['svg']['aria-label'] = true;
        $allowed['svg']['focusable'] = true;

        // Warning icon may use classes on paths/polygons.
        if (!isset($allowed['path']) || !is_array($allowed['path'])) {
            $allowed['path'] = [];
        }

        $allowed['path']['class'] = true;
        $allowed['path']['d'] = true;
        $allowed['path']['style'] = true;
        $allowed['path']['fill'] = true;
        $allowed['path']['fill-opacity'] = true;
        $allowed['path']['fill-rule'] = true;
        $allowed['path']['stroke'] = true;
        $allowed['path']['stroke-width'] = true;
        $allowed['path']['stroke-miterlimit'] = true;

        if (!isset($allowed['polygon']) || !is_array($allowed['polygon'])) {
            $allowed['polygon'] = [];
        }

        $allowed['polygon']['class'] = true;
        $allowed['polygon']['points'] = true;
        $allowed['polygon']['style'] = true;
        $allowed['polygon']['fill'] = true;
        $allowed['polygon']['stroke'] = true;
        $allowed['polygon']['stroke-width'] = true;
        $allowed['polygon']['stroke-miterlimit'] = true;

        return $allowed;
    }

    /**
     * Normalize SDK HTML to survive wp_kses without losing key visuals.
     *
     * - Removes <style> blocks (wp_kses will strip them anyway, and their content can become stray text nodes).
     * - Inlines the warning icon fill color when the SDK uses class="st0" + a removed <style> rule.
     */
    private static function normalizeWidgetHtml(string $html): string
    {
        // Remove style tags completely.
        $html = (string) preg_replace('~<style[^>]*>.*?</style>~is', '', $html);

        // Inline fill color for elements that rely on the removed CSS class.
        // Note: This is intentionally simple because the SDK markup uses class="st0" without an existing fill attribute.
        $html = str_replace('class="st0"', 'class="st0" fill="#AA1E1E"', $html);
        $html = str_replace("class='st0'", "class='st0' fill='#AA1E1E'", $html);

        return $html;
    }
}
