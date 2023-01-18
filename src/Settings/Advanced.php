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
use Resursbank\Ecom\Exception\CollectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\Filesystem;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\CacheDir;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\LogDir;
use Resursbank\Woocommerce\Database\Options\LogLevel;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Throwable;
use WC_Logger;

use function is_array;
use function is_dir;
use function is_string;

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
     * @todo Refactor, method too big, move translations to ECom. WOO-897. Remove phpcs:ignore when completed.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'store_id' => self::getStoreIdSetting(),
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_dir' => self::getCacheDirSetting(),
                'get_address_enabled' => self::getGetAddressEnabledSetting(),
            ],
        ];
    }

    /**
     * Resolve log handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @todo Refactor this method. WOO-872. Remove the PHPCS & PHPMD suppression.
     */
    // phpcs:ignore
    public static function getLogger(): LoggerInterface
    {
        $result = new NoneLogger();

        try {
            $path = LogDir::getData();

            if ($path === '') {
                $path = 'wc-logs';
            }

            // Path-helper for complex instances.
            if ($path === 'wc-logs') {
                // If wc-logs are defined, we use the same log directory that WooCommerce sets up to be WC_LOG_PATH.
                // This is used to simplify the setup for some instances that do not know about their log path.
                $uploadDir = wp_upload_dir(create_dir: false);

                if (
                    is_array(value: $uploadDir) &&
                    isset($uploadDir['basedir']) &&
                    is_string(value: $uploadDir['basedir']) &&
                    $uploadDir['basedir'] !== ''
                ) {
                    $path = preg_replace(
                        pattern: '/\/$/',
                        replacement: '',
                        subject: $uploadDir['basedir'] . '/wc-logs/'
                    );
                }
            }

            if (
                is_string(value: $path) &&
                is_dir(filename: $path)
            ) {
                $result = new FileLogger(path: $path);
            }
        } catch (Throwable $e) {
            if (class_exists(class: WC_Logger::class)) {
                (new WC_Logger())->critical(
                    message: 'Resurs Bank: ' . $e->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * Return the configured log level
     */
    public static function getLogLevel(): EcomLogLevel
    {
        return LogLevel::getLogLevel();
    }

    /**
     * Resolve cache handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
     */
    public static function getCache(): CacheInterface
    {
        $result = new None();

        try {
            $path = CacheDir::getData();

            if (is_dir(filename: CacheDir::getData())) {
                $result = new Filesystem(path: $path);
            }
        } catch (Throwable $e) {
            if (class_exists(class: WC_Logger::class)) {
                (new WC_Logger())->critical(
                    message: 'Resurs Bank: ' . $e->getMessage()
                );
            }
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
     * @phpcsSuppress
     * @noinspection DuplicatedCode
     * @todo Refactor, remove phpcs:ignore below after. WOO-894
     */
    // phpcs:ignore
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
                /** @var Store $store */
                foreach (StoreRepository::getStores() as $store) {
                    $return[$store->id] = sprintf(
                        '%s: %s',
                        $store->nationalStoreId,
                        $store->name
                    );
                }
            } catch (Throwable $e) {
                // Log all errors in the admin panel regardless of where the exception comes from.
                WordPress::setGenericError(
                    exception: new Exception(
                        message: $e->getMessage(),
                        previous: $e
                    )
                );
                // Make sure we give the options array a chance to render an error instead of the fields so ensure
                // the setting won't be saved by mistake when APIs are down.
                throw $e;
            }
        }

        return $return;
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
     * Fetches the cache_dir setting
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getCacheDirSetting(): array
    {
        return [
            'id' => CacheDir::getName(),
            'type' => 'text',
            'title' => Translator::translate(phraseId: 'cache-path'),
            'desc' => Translator::translate(
                phraseId: 'leave-empty-to-disable-cache'
            ),
            'default' => CacheDir::getDefault(),
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
            $currentStoreOptions = self::getStoreSelector();
            $storeIdSetting = [
                'id' => StoreId::getName(),
                'title' => Translator::translate(phraseId: 'store-id'),
                'type' => 'select',
                'default' => StoreId::getDefault(),
                'options' => $currentStoreOptions,
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
