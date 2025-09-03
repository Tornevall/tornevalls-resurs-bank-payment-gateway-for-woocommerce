<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayBlocks;
use Resursbank\Woocommerce\Modules\Order\Filter\Failure;
use Resursbank\Woocommerce\Modules\Order\Filter\ThankYou;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;

/**
 * Module initialization class for functionality used by the frontend parts of plugin.
 */
class Frontend
{
    /**
     * Init various modules.
     */
    public static function init(): void
    {
        if (!Enabled::isEnabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [self::class, 'enableConsoleLogs'], 1);
        self::initAdditionalfields();
        if (WooCommerce::isUsingBlocksCheckout()) {
            GatewayBlocks::init();
        }

        Gateway::initFrontend();
        ThankYou::init();
        Failure::init();
        PartPayment::initFrontend();
        UniqueSellingPoint::init();
    }

    /**
     * Enable logging to console for widget code, but based on the configured logLevel.
     */
    public static function enableConsoleLogs(): void
    {
        echo "<script>
        function resursConsoleLog(message, logLevel = 'INFO') {
            if (typeof rbFrontendData !== 'undefined') {
                const currentLogLevel = rbFrontendData.logLevel;
                if (currentLogLevel === 'DEBUG' || (currentLogLevel === 'INFO' && logLevel === 'INFO')) {
                    console.log(message);
                }
            }
        }
    </script>";
    }

    private static function initAdditionalfields(): void
    {
        add_action('init', function () {
            if (!function_exists('woocommerce_register_additional_checkout_field')) {
                return;
            }

            $package = Package::container();
            $fields_service = $package->get(CheckoutFields::class);
            $core_fields = $fields_service->get_core_fields();
            if (!isset($core_fields['company'])) {
                return;
            }

            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            woocommerce_register_additional_checkout_field(array(
                'id' => RESURSBANK_MODULE_PREFIX . '/billing_resurs_government_id',
                'label' => Translator::translate(phraseId: 'customer-type-legal'),
                'location' => 'address',
                'type' => 'text',
                'required' => false,
                'index' => ($core_fields['company']['index'] ?? 25) + 1,
                'attributes' => array(
                    'autocomplete' => 'off',
                    'placeholder' => 'Ex. 556677-8899',
                )
            ));
        });
    }
}
