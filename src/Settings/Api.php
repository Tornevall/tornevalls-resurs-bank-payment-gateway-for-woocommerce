<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\GrantType;
use Resursbank\Ecom\Lib\Api\Scope;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Lib\Repository\Api\Mapi\GenerateToken;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Database\Options\Api\StoreCountryCode;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * API settings section.
 */
class Api
{
    public const SECTION_ID = 'api_settings';
    public const NAME_PREFIX = 'resursbank_';

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'api-settings');
    }

    /**
     * Register actions for this config section.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        // Set priority high so that our method is called after credentials are saved
        add_action(
            'updated_option',
            'Resursbank\Woocommerce\Settings\Api::verifyCredentials',
            100,
            1
        );
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'enabled' => self::getEnabled(),
                'environment' => self::getEnvironment(),
                'client_id' => self::getClientId(),
                'client_secret' => self::getClientSecret(),
                'store_id' => self::getStoreIdSetting(),
                'store_country' => self::getStoreCountry(),
            ],
        ];
    }

    /**
     * Verifies that API credentials are valid and shows an error message if they're not.
     */
    public static function verifyCredentials(mixed $option): void
    {
        // Check if API section is what's being saved
        if (
            !(
                $option === 'resursbank_client_id' ||
                $option === 'resursbank_client_secret'
            )
        ) {
            return;
        }

        // Check if credentials have been properly entered
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();
        $environment = Environment::getData();
        $scope = $environment === EnvironmentEnum::PROD
            ? Scope::MERCHANT_API
            : Scope::MOCK_MERCHANT_API;

        try {
            $auth = new Jwt(
                clientId: $clientId,
                clientSecret: $clientSecret,
                scope: $scope,
                grantType: GrantType::CREDENTIALS
            );
        } catch (Throwable $error) {
            MessageBag::addError(message: $error->getMessage());
            return;
        }

        // Check if we can fetch a token
        try {
            (new GenerateToken(auth: $auth))->call();
        } catch (Throwable $error) {
            MessageBag::addError(message: $error->getMessage());
            return;
        }

        try {
            // Clean up MessageBag on successful requests.
            MessageBag::clear();
        } catch (Throwable) {
        }
    }

    /**
     * Get Enabled setting array.
     */
    private static function getEnabled(): array
    {
        return [
            'id' => Enabled::getName(),
            'title' => Translator::translate(phraseId: 'enabled'),
            'type' => 'checkbox',
            'default' => Enabled::getDefault(),
        ];
    }

    /**
     * Get Environment setting array.
     */
    private static function getEnvironment(): array
    {
        return [
            'id' => Environment::getName(),
            'title' => Translator::translate(phraseId: 'environment'),
            'type' => 'select',
            'options' => [
                EnvironmentEnum::TEST->value => Translator::translate(
                    phraseId: 'test'
                ),
                EnvironmentEnum::PROD->value => Translator::translate(
                    phraseId: 'prod'
                ),
            ],
            'custom_attributes' => ['size' => 1],
            'default' => Environment::getDefault(),
        ];
    }

    /**
     * Return config value for current store countryCode.
     */
    private static function getStoreCountry(): array
    {
        return [
            'id' => self::NAME_PREFIX . '_store_country',
            'type' => 'text',
            'custom_attributes' => [
                'disabled' => true,
            ],
            'title' => __('Country'),
            'value' => StoreCountryCode::getCurrentStoreCountry(),
            'css' => 'border: none; width: 100%; background: transparent; color: #000; box-shadow: none;',
        ];
    }

    /**
     * Get Client ID setting array.
     */
    private static function getClientId(): array
    {
        return [
            'id' => ClientId::getName(),
            'title' => Translator::translate(phraseId: 'client-id'),
            'type' => 'text',
            'default' => ClientId::getDefault()
        ];
    }

    /**
     * Get Client Secret setting array.
     */
    private static function getClientSecret(): array
    {
        return [
            'id' => ClientSecret::getName(),
            'title' => Translator::translate(phraseId: 'client-secret'),
            'type' => 'password',
            'default' => ClientSecret::getDefault(),
        ];
    }

    /**
     * Get the array for Store ID selector setting.
     */
    private static function getStoreIdSetting(): array
    {
        $result = [
            'id' => StoreId::getName(),
            'type' => 'select',
            'title' => Translator::translate(phraseId: 'store-id'),
            'default' => StoreId::getDefault(),
            'options' => [],
        ];

        try {
            // Both can cause Throwable, do them one at a time.
            $result['options'] = StoreRepository::getStores()->getSelectList();
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $result;
    }
}
