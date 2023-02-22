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
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableCache;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\LogDir;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
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

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'advanced');
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'store_id' => self::getStoreIdSetting(),
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_enabled' => self::getCacheEnabled(),
                'invalidate_cache' => self::getInvalidateCacheButton(),
                'get_address_enabled' => self::getGetAddressEnabled(),
                'authorization_callback_url' => self::getAuthorizationCallbackUrl(),
                'management_callback_url' => self::getManagementCallbackUrl(),
            ],
        ];
    }

    /**
     * Return field to display authorization callback URL template.
     */
    public static function getAuthorizationCallbackUrl(): array
    {
        $result = [];

        try {
            $result = [
                'id' => 'authorization_callback_url',
                'type' => 'text',
                'title' => Translator::translate(
                    phraseId: 'callback-url-authorization'
                ),
                'custom_attributes' => [
                    'readonly' => 'readonly',
                ],
                'default' => Url::getCallbackUrl(
                    type: CallbackType::AUTHORIZATION
                ),
            ];
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                msg: Translator::translate(
                    phraseId: 'generate-callback-template-failed'
                )
            );
        }

        return $result;
    }

    /**
     * Return field to display management callback URL template.
     */
    public static function getManagementCallbackUrl(): array
    {
        $result = [];

        try {
            $result = [
                'id' => 'management_callback_url',
                'type' => 'text',
                'title' => Translator::translate(
                    phraseId: 'callback-url-management'
                ),
                'custom_attributes' => [
                    'readonly' => 'readonly',
                ],
                'default' => Url::getCallbackUrl(
                    type: CallbackType::MANAGEMENT
                ),
            ];
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                msg: Translator::translate(
                    phraseId: 'generate-callback-template-failed'
                )
            );
        }

        return $result;
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

        // Default for multiple stores: avoid auto-selecting first store.
        $return = [
            '' => Translator::translate(phraseId: 'please-select'),
        ];

        if ($clientId !== '' && $clientSecret !== '') {
            try {
                $return = array_merge($return, self::getStores());
            } catch (Throwable $exception) {
                MessageBag::addError(
                    msg: Translator::translate(phraseId: 'get-stores-failed')
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
     * Return array for Log Dir/Path setting.
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
     * Return array for Log Level setting.
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
     * Return array for Cache Enabled setting.
     */
    private static function getCacheEnabled(): array
    {
        return [
            'id' => EnableCache::getName(),
            'title' => Translator::translate(phraseId: 'cache-enabled'),
            'type' => 'checkbox',
            'default' => EnableCache::getDefault(),
        ];
    }

    /**
     * Return array for Invalidate Cache button setting.
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
     * Return array for Get Address Enabled setting.
     */
    private static function getGetAddressEnabled(): array
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
     * Get the array for Store ID selector setting.
     */
    private static function getStoreIdSetting(): array
    {
        $result = [
            'id' => StoreId::getName(),
            'type' => 'select',
            'title' => Translator::translate(phraseId: 'store-id'),
        ];

        try {
            // Both can cause Throwable, do them one at a time.
            $result['default'] = StoreId::getDefault();
            $result['options'] = self::getStoreSelector();
        } catch (Throwable $e) {
            $result = array_merge($result, [
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly',
                ],
            ]);

            // @todo Error message displayed in admin trails one page load.
            Log::error(
                error: $e,
                msg: Translator::translate(phraseId: 'get-stores-failed')
            );
        }

        return $result;
    }
}
