<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Database\Options\Api\StoreCountryCode;
use Resursbank\Woocommerce\Util\Admin;
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
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            add_action('admin_notices', static function () use ($error): void {
                Admin::getAdminErrorNote(message: $error->getMessage());
            });

            Log::error(error: $error);
        }

        return $result;
    }
}
