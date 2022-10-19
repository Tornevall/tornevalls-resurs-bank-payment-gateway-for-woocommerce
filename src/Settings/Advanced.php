<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

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
     * @return array[]
     */
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
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc' => __(
                        'Leave empty to disable logging.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'cache_dir' => [
                    'id' => CacheDir::getName(),
                    'type' => 'text',
                    'title' => __(
                        'Cache path',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'desc' => __(
	                    'Leave empty to disable cache.',
	                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'default' => '',
                ],
            ]
        ];
    }

    /**
     * Resolve log handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
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
     * Resolve cache handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
     *
     * @return CacheInterface
     */
    public static function getCache(): CacheInterface
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
