<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Ecom\Lib\Model\UserSettings;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Advanced settings section.
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
     *
     * @throws ConfigException
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'log_enabled' => self::getLogEnabledSetting(),
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_enabled' => self::getCacheEnabled(),
                'invalidate_cache' => self::getInvalidateCacheButton(),
                'get_address_enabled' => self::getGetAddressEnabled(),
                'api_timeout' => self::getApiTimeout(),
                'xdebug_session_value' => self::getXDebugSessionValue()
            ]
        ];
    }

    /**
     * Return array for Enable log setting.
     */
    private static function getLogEnabledSetting(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::LOG_ENABLED),
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'title' => Translator::translate(phraseId: 'log-enabled'),
            'default' => Repository::getDefault(field: Field::LOG_ENABLED) ? 'yes' : 'no'
        ];
    }

    /**
     * Return array for Log Dir/Path setting.
     */
    private static function getLogDirSetting(): array
    {
        $default = Reader::getDefaultLogDir();

        // @todo The desc says leave empty to disable logging, but right above we have a setting to enable/disable logging. This is a bit confusing, needs rewording or removing that description since clearing the field clearly means no logs will be written anyways.
        return [
            'id' => Reader::getOptionName(field: Field::LOG_DIR),
            'type' => 'text',
            'title' => Translator::translate(phraseId: 'log-path'),
            'desc' => Translator::translate(
                phraseId: 'leave-empty-to-disable-logging'
            ) . '<br>Default: ' . $default,
            'default' => $default,
        ];
    }

    /**
     * Return array for Log Level setting.
     *
     * @throws ConfigException
     */
    private static function getLogLevelSetting(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::LOG_LEVEL),
            'type' => 'select',
            'title' => Translator::translate(phraseId: 'log-level'),
            'desc' => Translator::translate(
                phraseId: 'log-level-description'
            ) . '<br>' . 'Default: ' . EcomLogLevel::INFO->name,
            'default' => Repository::getDefault(field: Field::LOG_LEVEL)->value,
            'options' => EcomLogLevel::getAssoc()
        ];
    }

    /**
     * Return array for Cache Enabled setting.
     */
    private static function getCacheEnabled(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::CACHE_ENABLED),
            'title' => Translator::translate(phraseId: 'cache-enabled'),
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'default' => Repository::getDefault(field: Field::CACHE_ENABLED) ? 'yes' : 'no',
        ];
    }

    /**
     * Return array for Invalidate Cache button setting.
     */
    private static function getInvalidateCacheButton(): array
    {
        return [
            'id' => Reader::NAME_PREFIX . 'invalidate_cache',
            'title' => Translator::translate(phraseId: 'clear-cache'),
            'type' => 'rbinvalidatecachebutton'
        ];
    }

    /**
     * Return array for Get Address Enabled setting.
     *
     * @todo missing translations.
     */
    private static function getGetAddressEnabled(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::ENABLE_GET_ADDRESS),
            'type' => 'checkbox',
            'title' => Translator::translate(
                phraseId: 'enable-widget-to-get-address'
            ),
            'desc' => __('Yes'),
            'default' => Repository::getDefault(field: Field::ENABLE_GET_ADDRESS) ? 'yes' : 'no',
            'desc_tip' => 'Only available in Sweden.',
        ];
    }

    /**
     * Timeout settings for API requests.
     */
    private static function getApiTimeout(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::API_TIMEOUT),
            'type' => 'text',
            'title' => 'API Timeout (in seconds)',
            'default' => UserSettings::DEFAULT_API_TIMEOUT,
        ];
    }

    /**
     * Enabling xdebug where xdebug are usually hard to reach (like callbacks and other backend sessions).
     */
    private static function getXDebugSessionValue(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::XDEBUG_SESSION_VALUE),
            'title' => Translator::translate(phraseId: 'xdebug-session-value'),
            'type' => 'text',
            'default' => (string) Repository::getDefault(field: Field::XDEBUG_SESSION_VALUE),
            'desc' => Translator::translate(phraseId: 'enable-developer-mode-comment'),
        ];
    }
}
