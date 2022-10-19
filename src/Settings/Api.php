<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Database\Options\StoreId;

/**
 * API settings section.
 *
 * @todo Translations should be moved to ECom. See WOO-802 & ECP-205.
 */
class Api
{
    public const SECTION_ID = 'api_settings';
    public const SECTION_TITLE = 'API Settings';

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @return array[]
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'store_id' => [
                    'id' => StoreId::getName(),
                    'title' => 'Store ID',
                    'type' => 'text',
                    'default' => '',
                ],
                'client_id' => [
                    'id' => ClientId::getName(),
                    'title' => 'Client ID',
                    'type' => 'text',
                    'default' => '',
                ],
                'client_secret' => [
                    'id' => ClientSecret::getName(),
                    'title' => 'Client Secret',
                    'type' => 'password',
                    'default' => '',
                ],
                'environment' => [
                    'id' => Environment::getName(),
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
