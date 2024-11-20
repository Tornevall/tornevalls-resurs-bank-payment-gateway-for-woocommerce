<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\Logo\Widget;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Resursbank\Ecom\Module\Store\Enum\Country;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * This class adds support for Resurs Bank payment methods in the WooCommerce
 * blocks based checkout.
 */
final class GatewayBlocks extends AbstractPaymentMethodType
{
    /**
     * @inerhitDoc
     */
    protected $name = 'resursbank';

    /**
     * Register custom CSS and
     */
    public static function initFrontend(): void
    {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            fn(PaymentMethodRegistry $payment_method_registry) => $payment_method_registry->register((new self()))
        );

        wp_register_style(
            'rb-wc-blocks-css',
            Url::getResourceUrl(
                module: 'Gateway',
                file: 'checkout-blocks.css',
                type: ResourceType::CSS
            ),
        );

        wp_enqueue_style('rb-wc-blocks-css');
    }

    /**
     * Initialize block. Method is required by WooCommerce.
     *
     * @return void
     */
    public function initialize()
    {
        // WooCommerce requires this method to be implemented.
    }

    /**
     * Gateway is active if the plugin is enabled.
     *
     * @return bool
     */
    public function is_active()
    {
        return Enabled::isEnabled();
    }

    /**
     * Register JavaScript code for our gateway.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'rb-wc-blocks-js',
            Url::getAssetUrl(file: 'gateway.js'),
            ['react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data'],
	        '101f57a1921624052624',
	        true // Load script in footer.
        );

        wp_script_add_data('rb-wc-blocks-js', 'type', 'module');

        return array('rb-wc-blocks-js');
    }

    /**
     * Get data for payment gateway, will render to JS.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $result = [
			'allowed_country' => $this->getAllowedCountry(),
	        'payment_methods' => [],
        ];

        try {
            /** @var PaymentMethod $paymentMethod */
            foreach (Repository::getPaymentMethods() as $paymentMethod) {
                $usp = Repository::getUniqueSellingPoint($paymentMethod, (float) WC()?->cart?->total);
                $logo = new Widget($paymentMethod);

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
	 * @return Country
	 */
	private function getAllowedCountry()
	{
		try {
			return StoreRepository::getConfiguredStore()?->countryCode ?? Country::UNKNOWN;
		} catch (Throwable $error) {
			Log::error(error: $error);
		}

		return Country::UNKNOWN;
	}
}
