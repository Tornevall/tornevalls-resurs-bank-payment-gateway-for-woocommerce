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
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\Advanced\CacheEnabled;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\LogDir;
use Resursbank\Woocommerce\Database\Options\LogLevel;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Modules\Gateway\ResursDefault;
use Throwable;

/**
 * Advanced settings section.
 *
 * @todo Translations should be moved to ECom. See WOO-802 & ECP-205.
 * @todo Directory validation should be centralised in ECom. See WOO-803 & ECP-202.
 */
class Advanced
{
    public const SECTION_ID = 'advanced';
    public const SECTION_TITLE = 'Advanced Settings';

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @return array<array>
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws IllegalTypeException
     */
    public static function getSettings(): array
    {
        try {
            $callbackUrlSetup = Advanced::getCallbackUrlSetup();
        } catch (Throwable) {
            $callbackUrlSetup = [];
        }

        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'store_id' => self::getStoreIdSetting(),
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_enabled' => self::getCacheEnabled(),
                'invalidate_cache' => self::getInvalidateCacheButton(),
                'get_address_enabled' => self::getGetAddressEnabledSetting(),
                'callback_url' => $callbackUrlSetup,
            ],
        ];
    }

    /**
     * @throws IllegalValueException
     */
    public static function getCallbackUrlSetup(): array
    {
        return [
            'id' => 'callback_url',
            'type' => 'text',
            'title' => 'Callback URL Template',
            'custom_attributes' => [
                'readonly' => 'readonly',
            ],
            'default' => (new ResursDefault())->getCallbackUrl(
                callbackType: CallbackType::AUTHORIZATION
            ),
        ];
    }

    /**
     * Fetch options for the log level selector
     *
     * @return array
     */
    private static function getLogLevelOptions(): array
    {
        $options = [];

        foreach (EcomLogLevel::cases() as $case) {
            $options[$case->value] = $case->name;
        }

        return $options;
    }

    /**
     * Render an array with available stores for a merchant, based on their national store id as this is shorter
     * than the full store uuid. The national id is a human-readable variant of the uuid.
     *
     * @return array
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     */
    private static function getStoreSelector(): array
    {
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        // Default for multiple stores: Never putting merchants on the first available choice.
        $return = [
            '' => 'Select Store',
        ];

        if ($clientId !== '' && $clientSecret !== '') {
            try {
                $return = array_merge($return, self::getStores());
            } catch (Throwable $exception) {
                WordPress::setGenericError(
                    exception: new Exception(
                        message: $exception->getMessage(),
                        previous: $exception
                    )
                );
                // Make sure we give the options array a chance to render an error instead of the fields so ensure
                // the setting won't be saved by mistake when APIs are down.
                throw $exception;
            }
        }

        return $return;
    }

    /**
     * Fetch array of stores for store selector.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    private static function getStores(): array
    {
        $stores = [];

        /** @var Store $store */
        foreach (StoreRepository::getStores() as $store) {
            $stores[$store->id] = $store->nationalStoreId . ': ' . $store->name;
        }

        return $stores;
    }

    /**
     * Fetches log_dir setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getLogDirSetting(): array
    {
        return [
            'id' => LogDir::getName(),
            'type' => 'text',
            'title' => Translator::translate(phraseId: 'log-path'),
            'desc' => Translator::translate(
                phraseId: 'leave-empty-to-disable-logging'
            ),
            'default' => LogDir::getDefault(),
        ];
    }

    /**
     * Fetches the log_level setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getLogLevelSetting(): array
    {
        return [
            'id' => LogLevel::getName(),
            'type' => 'select',
            'title' => Translator::translate(phraseId: 'log-level'),
            'desc' => Translator::translate(phraseId: 'log-level-description'),
            'default' => EcomLogLevel::INFO->value,
            'options' => self::getLogLevelOptions(),
        ];
    }

    /**
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getCacheEnabled(): array
    {
        return [
            'id' => CacheEnabled::getName(),
            'title' => Translator::translate(phraseId: 'cache-enabled'),
            'type' => 'checkbox',
            'default' => CacheEnabled::getDefault(),
        ];
    }

    /**
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getInvalidateCacheButton(): array
    {
        return [
            'id' => Option::NAME_PREFIX . 'invalidate_cache',
            'title' => Translator::translate(phraseId: 'clear-cache'),
            'type' => 'rbinvalidatecachebutton',
        ];
    }

    /**
     * Fetches the get_address_enabled setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getGetAddressEnabledSetting(): array
    {
        return [
            'id' => EnableGetAddress::getName(),
            'type' => 'checkbox',
            'title' => Translator::translate(
                phraseId: 'enable-widget-to-get-address'
            ),
            'desc' => '',
            'default' => EnableGetAddress::getDefault(),
        ];
    }

    /**
     * Fetches the store id setting.
     */
    private static function getStoreIdSetting(): array
    {
        try {
            $storeIdSetting = [
                'id' => StoreId::getName(),
                'title' => Translator::translate(phraseId: 'store-id'),
                'type' => 'select',
                'default' => StoreId::getDefault(),
                'options' => self::getStoreSelector(),
            ];
        } catch (Throwable $e) {
            $storeIdSetting = [
                'id' => StoreId::getName(),
                'title' => Translator::translate(phraseId: 'store-id'),
                'type' => 'title',
                'default' => StoreId::getDefault(),
                'desc_tip' => true,
                'desc' => sprintf(
                    'Could not fetch stores from Resurs Bank: %s.',
                    $e->getMessage()
                ),
            ];
        }

        return $storeIdSetting;
    }
}
