<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\Filesystem;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Woocommerce\Database\Options\CacheDir;
use Resursbank\Woocommerce\Database\Options\LogDir;
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
     * @return array
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

            // Path-helper for complex instances.
            if ($path === 'wc-logs') {
                // If wc-logs are defined, we use the same log directory that WooCommerce sets up to be WC_LOG_PATH.
                // This is used to simplify the setup for some instances that do not know about their log path.
                /** @var array $uploadDir */
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
}
