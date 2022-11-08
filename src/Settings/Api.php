<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CollectionException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Models\StoreCollection;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Module\Data;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Enabled;
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
        try {
            $currentStoreOptions = self::getStoreSelector();
            $storeIdSetting = [
                'id' => StoreId::getName(),
                'title' => 'Store ID',
                'type' => 'select',
                'default' => '',
                'options' => $currentStoreOptions
            ];
        } catch (Exception $e) {
            $storeIdSetting = [
                'id' => StoreId::getName(),
                'title' => 'Store ID',
                'type' => 'title',
                'default' => '',
                'desc_tip' => true,
                'desc' => sprintf('Could not fetch stores from Resurs Bank: %s.', $e->getMessage())
            ];
        }

        // Note: The yes/no values are used as booleans in woocommerce.
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'enabled' => [
                    'id' => Enabled::getName(),
                    'title' => 'Gateway Enabled',
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc' => 'Enabled',
                ],
                'store_id' => $storeIdSetting,
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
                    'title' => __('Environment', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'test' => __(
                            'Test',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'prod' => __(
                            'Production',
                            'resurs-bank-payments-for-woocommerce'
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
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CollectionException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @noinspection DuplicatedCode
     */
    private static function getStoreSelector(): array
    {
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        // Default for multiple stores: Never putting merchants on the first available choice.
        $return = [
            '' => 'Select Store'
        ];

        if ($clientId !== '' && $clientSecret !== '') {
            try {
                $storeList = StoreRepository::getStores();
                foreach ($storeList as $store) {
                    $return[$store->id] = sprintf('%s: %s', $store->nationalStoreId, $store->name);
                }
            } catch (Exception $e) {
                // Log all errors in the admin panel regardless of where the exception comes from.
                WordPress::setGenericError($e);
                // Make sure we give the options array a chance to render an error instead of the fields so ensure
                // the setting won't be saved by mistake when APIs are down.
                throw $e;
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
