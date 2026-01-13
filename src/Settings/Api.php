<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Model\Store\Store;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;
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
        $properSection = (Admin::isSection('') || Admin::isSection(
            'api_settings'
        ));

        if (
            !Admin::isTab(tabName: RESURSBANK_MODULE_PREFIX) ||
            !$properSection
        ) {
            return;
        }

        add_action(
            'admin_enqueue_scripts',
            'Resursbank\Woocommerce\Settings\Api::initScripts'
        );
    }

    /**
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initScripts(): void
    {
        wp_register_script(
            'rb-api-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Api',
                file: 'saved-updates.js'
            )
        );
        wp_enqueue_script(
            'rb-api-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Api',
                file: 'saved-updates.js'
            ),
            ['jquery']
        );

        wp_localize_script(
            'rb-api-admin-scripts-load',
            'rbApiAdminLocalize',
            [
                'url' => Route::getUrl(
                    route: Route::ROUTE_GET_STORE_COUNTRY
                ),
            ]
        );

        wp_enqueue_style(
            'rb-ga-css',
            Url::getResourceUrl(
                module: 'Api',
                file: 'api.css',
                type: ResourceType::CSS
            ),
            [],
            '1.0.0'
        );
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @throws ConfigException
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
                'store_country' => self::getStoreCountrySetting(),
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
     *
     * @throws ConfigException
     */
    private static function getStoreCountrySetting(): array
    {
        return [
            'id' => self::NAME_PREFIX . 'store_country',
            'type' => 'text',
            'custom_attributes' => [
                'disabled' => true,
            ],
            'title' => __('Country'),
            'value' => WooCommerce::getStoreCountry(),
            'css' => 'border: none; width: 100%; background: transparent; color: #000; box-shadow: none;',
        ];
    }

    /**
     * Get a Client ID setting array.
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
            // Do not fetch stores until credentials are present.
            if (ClientId::getData() !== '' && ClientSecret::getData() !== '') {
                $result['options'] = StoreRepository::getStores()->getSelectList();

                // If no store has been selected in the configuration, default to the first store,
                // as this will be displayed in the dropdown after saving. This ensures the merchant
                // has a value saved, even if no store was selected initially.
                if (self::credentialsAreSet()) {
                    $result['options'] = StoreRepository::getStores()->getSelectList();
                    self::setDefaultStoreIfNoneSelected();
                }
            }
        } catch (Throwable $error) {
            self::logAndHandleError(error: $error);
        }

        return $result;
    }

    /**
     * Are credentials set?
     */
    private static function credentialsAreSet(): bool
    {
        return ClientId::getData() !== '' && ClientSecret::getData() !== '';
    }

    /**
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     */
    private static function setDefaultStoreIfNoneSelected(): void
    {
        if (StoreId::getData() !== '') {
            return;
        }

        $firstStore = StoreRepository::getStores()->getFirst();

        if (!($firstStore instanceof Store)) {
            return;
        }

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        update_option(StoreId::getName(), $firstStore->id);
    }

    /**
     * Log and handle visible error messages.
     */
    private static function logAndHandleError(Throwable $error): void
    {
        // Some errors cannot be rendered through the admin_notices. Avoid that action.
        Admin::getAdminErrorNote(message: $error->getMessage());
        Log::error(error: $error);
    }
}
