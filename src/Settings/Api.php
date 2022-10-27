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
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Models\StoreCollection;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Module\Data;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Option;
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
                    'type' => 'select',
                    'default' => '',
                    'options' => self::getStoreSelector()
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
     * Render an array with available stores for a merchant, based on their national store id as this is shorter
     * than the full store uuid. The national id is a human-readable variant of the uuid.
     * @return array
     * @noinspection DuplicatedCode
     */
    private static function getStoreSelector() {
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        // Default for multiple stores: Never putting merchants on the first available choice.
        $return = [
            '' => 'Select Store'
        ];

        if ($clientId !== '' && $clientSecret !== '') {
            try {
                $storeList = StoreRepository::getStores();
                if ($storeList->count() > 1) {
                    /** @var Store $store */
                    foreach ($storeList as $store) {
                        $return[$store->id] = sprintf('%s: %s', $store->nationalStoreId, $store->name);
                    }
                } elseif ($storeList->count() === 1) {
                    /** @var Store $store */
                    $store = $storeList->current();

                    $return = [sprintf('%s: %s', $store->nationalStoreId, $store->name)];

                    // If only one store is available, we can set it as the stored value.
                    StoreId::setData($store->id);
                }
            } catch (Exception $e) {
                // @todo Consider using WC logger to output message in admin panel, regardless of the exception type.
                // @todo We're always landing here, when the client ID and secret are not valid for sure, but other
                // @todo errors may occur here too. As an error, they should be ignored.
                WordPress::setGenericError($e);
            }
        }

        return $return;
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
