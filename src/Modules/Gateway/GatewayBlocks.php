<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps


declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Enum\Country;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Ecom\Module\Widget\Logo\Html as LogoWidget;
use Resursbank\Ecom\Module\Widget\ReadMore\Html as ReadMoreWidget;
use Resursbankabpayments\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbankabpayments\Woocommerce\Database\Options\Api\Enabled;
use Resursbankabpayments\Woocommerce\Util\HtmlSanitizer;
use Resursbankabpayments\Woocommerce\Util\Log;
use Resursbankabpayments\Woocommerce\Util\ResourceType;
use Resursbankabpayments\Woocommerce\Util\Route;
use Resursbankabpayments\Woocommerce\Util\Url;
use Resursbankabpayments\Woocommerce\Util\UserAgent;
use Resursbankabpayments\Woocommerce\Util\WooCommerce;
use Throwable;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * This class adds support for Resurs Bank payment methods in the WooCommerce
 * blocks based checkout.
 */
final class GatewayBlocks extends AbstractPaymentMethodType
{
    /** @inheritdoc */ // phpcs:ignore
    protected $name = 'resursbank';

    /**
     * Register custom CSS and
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        if (empty(StoreId::getData())) {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function (PaymentMethodRegistry $payment_method_registry): void {
                $payment_method_registry->register(
                    (new self())
                );
            }
        );

        // Note that despite the naming this function also confirm whether we
        // are currently rendering the blocks based checkout page.
        if (!WooCommerce::isUsingBlocksCheckout()) {
            return;
        }

        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    /**
     * Enqueue assets for the checkout block.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function enqueueAssets(): void
    {
        wp_register_style(
            'resursbankabpaygw-wc-blocks-css',
            Url::getResourceUrl(
                module: 'Gateway',
                file: 'checkout-blocks.css',
                type: ResourceType::CSS
            ),
            [],
            UserAgent::getPluginVersion()
        );

        wp_enqueue_style('resursbankabpaygw-wc-blocks-css');
    }

    /**
     * Initializes the integration block.
     *
     * This method is required by WooCommerce's architecture and acts as an entry point
     * for any necessary configuration or dependency setup related to the integration.
     * While Resurs-specific logic could theoretically be registered here, the dynamic
     * nature of our payment methods necessitates a different approach.
     *
     * Instead, the actual registration occurs in the `get_payment_method_data` method.
     * This design allows GatewayBlocks to function as modular placeholders, delegating
     * specific functionality to smaller submodules for enhanced flexibility and scalability.
     */
    public function initialize(): void
    {
        // Placeholder for potential Resurs-specific initialization logic, if needed.
    }

    /**
     * Gateway is active if the plugin is enabled.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function is_active(): bool
    {
        return Enabled::isEnabled();
    }

    /**
     * Register JavaScript code for our gateway.
     *
     * @return array<string>
     * @throws EmptyValueException
     * @throws FilesystemException
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'resursbankabpaygw-wc-blocks-js',
            Url::getAssetUrl(file: 'gateway.js'),
            ['react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data'],
            WooCommerce::getAssetVersion(),
            // Load script in footer.
            true
        );

        wp_script_add_data('resursbankabpaygw-wc-blocks-js', 'type', 'module');

        return ['resursbankabpaygw-wc-blocks-js'];
    }

    /**
     * Get data for payment gateway, will render to JS.
     *
     * NOTE: The HTML snippets below originate from an SDK.
     * We sanitize them via wp_kses with an allowlist that preserves required behavior
     * (iframe, data-* attributes, ids) while preventing XSS.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function get_payment_method_data(): array
    {
        $result = [
            'allowed_country' => $this->getAllowedCountry(),
            'payment_methods' => [],
        ];

        try {
            /** @var PaymentMethod $paymentMethod */
            foreach (Repository::getPaymentMethods() as $paymentMethod) {
                $logo = new LogoWidget(paymentMethod: $paymentMethod);
                $helper = new GatewayHelper(paymentMethod: $paymentMethod);

                try {
                    $usp = Repository::getUniqueSellingPoint(
                        paymentMethod: $paymentMethod,
                        amount: (float)WC()?->cart?->total
                    );
                    $uspText = $usp->getText();
                    $costList = $helper->getCostList();
                    $priceSignageWarning = $helper->getPriceSignageWarning();
                    $readMore = new ReadMoreWidget(
                        paymentMethod: $paymentMethod,
                        amount: (float)WC()?->cart?->total
                    );
                } catch (Throwable $error) {
                    Log::error(error: $error);
                    continue;
                }

                // Sanitize SDK HTML before sending it to JS.
                $descriptionHtml = '<div class="rb-usp">' . ($uspText ?? '') . '</div>';

                $result['payment_methods'][] = [
                    'name' => $paymentMethod->id,
                    'title' => $paymentMethod->name,
                    'description' => self::sanitizeBlocksWidgetHtml(
                        $descriptionHtml
                    ),
                    'costlist' => self::sanitizeBlocksWidgetHtml(
                        $costList ?? ''
                    ),
                    'costlist_url' => Route::getUrl(route: 'get-costlist'),
                    'readmore' => self::sanitizeBlocksWidgetHtml(
                        $readMore->content ?? ''
                    ),
                    'price_signage_warning' => self::sanitizeBlocksWidgetHtml(
                        $priceSignageWarning ?? ''
                    ),
                    'read_more_css' => '',
                    'logo' => self::sanitizeBlocksWidgetHtml(
                        $logo->content ?? ''
                    ),
                    'logo_type' => $logo->getIdentifier(),
                    'min_purchase_limit' => $paymentMethod->minPurchaseLimit,
                    'max_purchase_limit' => $paymentMethod->maxPurchaseLimit,
                    'enabled_for_legal_customer' => $paymentMethod->enabledForLegalCustomer,
                    'enabled_for_natural_customer' => $paymentMethod->enabledForNaturalCustomer,
                    'read_more_url' => $usp->readMore->url,
                ];
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $result;
    }

    /**
     * Sanitize SDK-generated HTML for Blocks checkout.
     *
     * Delegates to centralized HtmlSanitizer utility which handles:
     * - ReadMore widget functionality (data-* attributes, id, iframe)
     * - SVG logos (defs/g/path/polygon)
     * - Defense-in-depth iframe src validation
     * - Safe inline style properties
     * - Preventing script injection via wp_kses
     *
     * @param string $html HTML to sanitize
     * @return string Sanitized HTML safe for output
     */
    private static function sanitizeBlocksWidgetHtml(string $html): string
    {
        $html = (string)$html;

        if ($html === '') {
            return '';
        }

        // Normalize SDK patterns that break when <style> is removed.
        $html = HtmlSanitizer::normalizeWidgetHtml($html);

        // Remove iframes with unexpected src (defense-in-depth).
        $html = (string)preg_replace_callback(
            '~<iframe\b([^>]*\bsrc\s*=\s*(["\'])(.*?)\2[^>]*)>(.*?)</iframe>~is',
            callback: static function (array $m): string {
                $src = (string)$m[3];

                if (!HtmlSanitizer::isAllowedIframeSrc($src)) {
                    return '';
                }

                return $m[0];
            },
            subject: $html
        );

        $allowed = HtmlSanitizer::getBlocksWidgetAllowlist();

        // Temporarily extend safe_style_css to allow widget-required CSS properties.
        return HtmlSanitizer::withSafeStyleCss(
            HtmlSanitizer::getBlocksWidgetSafeStyles(),
            static fn (): string => wp_kses($html, $allowed)
        );
    }

    /**
     * Check if the payment method is available for the current country.
     */
    private function getAllowedCountry(): Country
    {
        try {
            return StoreRepository::getConfiguredStore()?->countryCode ?? Country::UNKNOWN;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return Country::UNKNOWN;
    }
}
