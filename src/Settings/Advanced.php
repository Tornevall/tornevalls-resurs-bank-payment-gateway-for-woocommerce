<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

namespace Resursbank\Woocommerce\Settings;

use Exception;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\Filesystem;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Woocommerce\Database\Options\CacheDir;
use Resursbank\Woocommerce\Database\Options\LogDir;

use WC_Logger;
use function is_string;
use function is_dir;

/**
 * Advanced settings section and fields for WooCommerce.
 */
class Advanced
{
    public const SECTION_ID = 'advanced';
    public const SECTION_TITLE = 'Advanced Settings';

    /**
     * Returns a list of settings fields. This array is meant to be used by
     * WooCommerce to convert them to HTML and render them.
     *
     * @return array[]
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'log_dir' => [
                    'id' => LogDir::NAME,
                    'type' => 'text',
                    'title' => __(
                        'Log path',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc' => __(
                        'To enable logging, you actively have to fill in the path for where you want to keep them. ' .
                        'To avoid data leaks we suggest that this path not be accessible from the internet.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'cache_dir' => [
                    'id' => CacheDir::NAME,
                    'type' => 'text',
                    'title' => __(
                        'Cache path',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                ],
            ]
        ];
    }

    /**
     * Returns the logger that should be used by the settings. If no directory
     * for the logs has been specified in the settings, then a dummy logger with
     * no real functionality will be returned.
     *
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        $result = new NoneLogger();

        try {
            $path = LogDir::getData();

            if (is_string(value: $path) &&
                is_dir(filename: LogDir::getData())
            ) {
                $result = new FileLogger(path: $path);
            }
        } catch (Exception $e) {
            if (class_exists(class: WC_Logger::class)) {
                (new WC_Logger())->critical('Resurs Bank: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Returns the logger that should be used by the settings. If no directory
     * for the logs has been specified in the settings, then a dummy logger with
     * no real functionality will be returned.
     *
     * @return CacheInterface
     */
    public static function getCacher(): CacheInterface
    {
        $result = new None();

        try {
            $path = CacheDir::getData();

            if (is_string(value: $path) &&
                is_dir(filename: CacheDir::getData())
            ) {
                $result = new Filesystem(path: $path);
            }
        } catch (Exception $e) {
            if (class_exists(class: WC_Logger::class)) {
                (new WC_Logger())->critical('Resurs Bank: ' . $e->getMessage());
            }
        }

        return $result;
    }
}
