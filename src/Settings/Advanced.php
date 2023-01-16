<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\Filesystem;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Woocommerce\Database\Options\CacheDir;
use Resursbank\Woocommerce\Database\Options\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\LogDir;
use Resursbank\Woocommerce\Database\Options\LogLevel;
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
    // phpcs:ignore
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'log_dir' => [
                    'id' => LogDir::getName(),
                    'type' => 'text',
                    'title' => __(
                        'Log path',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'Leave empty to disable logging.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'log_level' => [
                    'id' => LogLevel::getName(),
                    'type' => 'select',
                    'title' => Translator::translate(phraseId: 'log-level'),
                    'desc' => Translator::translate(
                        phraseId: 'log-level-description'
                    ),
                    'default' => EcomLogLevel::INFO->value,
                    'options' => self::getLogLevelOptions(),
                ],
                'cache_dir' => [
                    'id' => CacheDir::getName(),
                    'type' => 'text',
                    'title' => __(
                        'Cache path',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'Leave empty to disable cache.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'get_address_enabled' => [
                    'id' => EnableGetAddress::getName(),
                    'type' => 'checkbox',
                    'title' => 'Enable widget to get address',
                    'desc' => '',
                    'default' => '',
                ],
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
}
