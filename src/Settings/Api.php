<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Exception;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Network\Model\Auth\Jwt;
use ResursBank\Service\WordPress;
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

    /**
     * @return Jwt|null
     * @throws Exception
     * @todo Use Enums and constants for scope / grant type. These should be declared in Ecom. Make it part of the above issues or create new ones.
     * @todo Fix credentials validation. See WOO-805 & ECP-206.
     */
    public static function getJwt(): Jwt|null
    {
        $result = null;
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        try {
            if ($clientId !== '' && $clientSecret !== '') {
                $result = new Jwt(
                    clientId: ClientId::getData(),
                    clientSecret: ClientSecret::getData(),
                    scope: Environment::getData() === 'test' ? 'mock-merchant-api' : 'merchant-api',
                    grantType: 'client_credentials',
                );
            } else {
                throw new Exception('Missing credentials');
            }
        } catch (Exception $e) {
            // Handle all errors the same way, regardless of the cause.
            WordPress::setGenericError($e);
        }

        return $result;
    }
}
