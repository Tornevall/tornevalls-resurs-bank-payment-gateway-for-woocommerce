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

/**
 * This class adds support for Resurs Bank payment methods in the WooCommerce
 * blocks based checkout.
 */
final class GatewayBlocks extends AbstractPaymentMethodType
{
    protected $name = 'resursbank';

    public static function initFrontend()
    {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            fn(PaymentMethodRegistry $payment_method_registry) => $payment_method_registry->register((new self()))
        );

        wp_enqueue_style(
            'resursbank-styles',
            plugin_dir_url(__FILE__) . 'assets/css/style.css'
        );
    }

    // @todo Confirm we need this or remove it.
    public function initialize()
    {
        $this->settings = [
            'enabled' => 'yes',
            'title' => 'Resurs Bank',
            'description' => 'Pay with Resurs Bank',
            'wanka' => 'wonka'
        ];
    }

    // @todo Return value based on settings.
    public function is_active()
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'wc-resursbank-blocks-integration',
            plugin_dir_url(__DIR__) . 'assets/js/dist/payment-method.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ),
            null, // or time() or filemtime( ... ) to skip caching
            true
        );
        wp_script_add_data('wc-resursbank-blocks-integration', 'type', 'module');

        return array('wc-resursbank-blocks-integration');

    }

    public function get_payment_method_data()
    {
        $result = [];

        /** @var PaymentMethod $paymentMethod */
        foreach (Repository::getPaymentMethods() as $paymentMethod) {
            // @todo fix total amount
            $usp = Repository::getUniqueSellingPoint($paymentMethod, 0);
            $logo = new Widget($paymentMethod);

            $result[] = [
                'name' => $paymentMethod->id,
                'title' => $paymentMethod->name,
                'description' => $usp->content,
                'read_more_css' => $usp->readMore->css,
                'logo' => $logo->html,
                'logo_type' => $logo->getIdentifier()
            ];
        }

        return $result;
    }
}
