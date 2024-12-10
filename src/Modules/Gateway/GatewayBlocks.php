<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps


declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\Logo\Widget;
use Resursbank\Ecom\Module\Store\Enum\Country;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * This class adds support for Resurs Bank payment methods in the WooCommerce
 * blocks based checkout.
 */
final class GatewayBlocks extends AbstractPaymentMethodType
{
    /** @inheritdoc */
    // phpcs:ignore
    protected $name = 'resursbank';

    /**
     * Register custom CSS and
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function (PaymentMethodRegistry $payment_method_registry): void {
                $payment_method_registry->register(new self());
            }
        );

        wp_register_style(
            'rb-wc-blocks-css',
            Url::getResourceUrl(
                module: 'Gateway',
                file: 'checkout-blocks.css',
                type: ResourceType::CSS
            )
        );

        wp_enqueue_style('rb-wc-blocks-css');
    }

    /**
     * Initialize block. Method is required by WooCommerce.
     */
    public function initialize(): void
    {
        // WooCommerce requires this method to be implemented.
    }

    /**
     * Gateway is active if the plugin is enabled.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function is_active(): bool
    {
        return Enabled::isEnabled();
    }

    /**
     * Register JavaScript code for our gateway.
     *
     * @return array<string>
     * @throws FilesystemException
     * @throws EmptyValueException
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'rb-wc-blocks-js',
            Url::getAssetUrl(file: 'gateway.js'),
            ['react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data'],
            WooCommerce::getAssetVersion(),
            // Load script in footer.
            true
        );

        wp_script_add_data('rb-wc-blocks-js', 'type', 'module');

        return array('rb-wc-blocks-js');
    }

    /**
     * Get data for payment gateway, will render to JS.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function get_payment_method_data(): array
    {
        $result = [
            'allowed_country' => $this->getAllowedCountry(),
            'payment_methods' => [],
            'is_admin' => Admin::isAdmin(),
        ];

        try {
            /** @var PaymentMethod $paymentMethod */
            foreach (Repository::getPaymentMethods() as $paymentMethod) {
                $usp = Repository::getUniqueSellingPoint(
                    paymentMethod: $paymentMethod,
                    amount: (float)WC()?->cart?->total
                );
                $logo = new Widget(paymentMethod: $paymentMethod);

                $result['payment_methods'][] = [
                    'name' => $paymentMethod->id,
                    'title' => $paymentMethod->name,
                    'description' => $usp->content,
                    'read_more_css' => $usp->readMore->css,
                    'logo' => $logo->html,
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
