<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Database\Options\StoreId;

/**
 * API settings section and fields for WooCommerce.
 */
class Api
{
    public const SECTION_ID = 'api_settings';
    public const SECTION_TITLE = 'API Settings';

    /**
     * Returns a list of settings fields. This array is meant to be used by
     * WooCommerce to convert them to HTML and render them.
     *
     * @return array[]
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'store_id' => [
                    'id' => StoreId::NAME,
                    'title' => 'Store ID',
                    'type' => 'text',
                    'default' => '',
                ],
                'client_id' => [
                    'id' => ClientId::NAME,
                    'title' => 'Client ID',
                    'type' => 'text',
                    'default' => '',
                ],
                'client_secret' => [
                    'id' => ClientSecret::NAME,
                    'title' => 'Client Password',
                    'type' => 'password',
                    'default' => '',
                ],
                'environment' => [
                    'id' => Environment::NAME,
                    'title' => __('Environment', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'test' => __(
                            'Test',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        'prod' => __(
                            'Production',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                    'default' => 'test',
                ],
            ]
        ];
    }
}
