<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableCache;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\LogDir;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Advanced settings section.
 *
 ** @todo Directory validation should be centralised in ECom. See WOO-803 & ECP-202.
 */
class Advanced
{
    public const SECTION_ID = 'advanced';

    public const NAME_PREFIX = 'resursbank_';

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
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_enabled' => self::getCacheEnabled(),
                'invalidate_cache' => self::getInvalidateCacheButton(),
                'get_address_enabled' => self::getGetAddressEnabled(),
            ],
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
}
