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
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\Logo\Widget;
use Resursbank\Ecom\Module\Store\Enum\Country;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
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

        if (!is_checkout()) {
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
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @noinspection PhpMissingParentCallCommonInspection
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

        return ['rb-wc-blocks-js'];
    }

    /**
     * Get data for payment gateway, will render to JS.
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
                $usp = Repository::getUniqueSellingPoint(
                    paymentMethod: $paymentMethod,
                    amount: (float)WC()?->cart?->total
                );
                $logo = new Widget(paymentMethod: $paymentMethod);
                $helper = new GatewayHelper(paymentMethod: $paymentMethod);

                $priceSignageContent = $paymentMethod->priceSignagePossible ?
                    $helper->getCostList() . $helper->getPriceSignageWarning() : '';

                $result['payment_methods'][] = [
                    'name' => $paymentMethod->id,
                    'title' => $paymentMethod->name,
                    'description' => $usp->content . $priceSignageContent,
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
