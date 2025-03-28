<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\Advanced\ApiTimeout;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableCache;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableGetAddress;
use Resursbank\Woocommerce\Database\Options\Advanced\ForcePaymentMethodSortOrder;
use Resursbank\Woocommerce\Database\Options\Advanced\LogDir;
use Resursbank\Woocommerce\Database\Options\Advanced\LogEnabled;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Database\Options\Advanced\SetMethodCountryRestriction;
use Resursbank\Woocommerce\Database\Options\Advanced\XDebugSessionValue;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;

/**
 * Advanced settings section.
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
     *
     * @throws ConfigException
     */
    public static function getSettings(): array
    {
        $return = [
            self::SECTION_ID => [
                'log_enabled' => self::getLogEnabledSetting(),
                'log_dir' => self::getLogDirSetting(),
                'log_level' => self::getLogLevelSetting(),
                'cache_enabled' => self::getCacheEnabled(),
                'invalidate_cache' => self::getInvalidateCacheButton(),
                'get_address_enabled' => self::getGetAddressEnabled(),
                'force_payment_method_sort_order' => self::getForcePaymentMethodSortOrder(),
                'set_method_country_restriction' => self::setMethodCountryRestriction(),
                'api_timeout' => self::getApiTimeout(),
                'xdebug_session_value' => self::getXDebugSessionValue()
            ]
        ];

        if (!EnableGetAddress::isCountryCodeSe()) {
            $return[self::SECTION_ID]['get_address_enabled'] = self::getCountryRestrictionConfig();
        }

        return $return;
    }

    /**
     * Return array for Enable log setting.
     */
    private static function getLogEnabledSetting(): array
    {
        return [
            'id' => LogEnabled::getName(),
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'title' => Translator::translate(phraseId: 'log-enabled'),
            'desc_tip' => 'Default: ' . LogEnabled::getDefault(),
            'default' => LogEnabled::getDefault()
        ];
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
            ) . '<br>Default: ' . LogDir::getDefault(),
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
            'desc' => Translator::translate(
                phraseId: 'log-level-description'
            ) . '<br>' . 'Default: ' . EcomLogLevel::INFO->name,
            'default' => EcomLogLevel::INFO->value,
            'options' => self::getLogLevelOptions(),
        ];
    }

    /**
     * Fetch options for the log level selector
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
     * Return array for Cache Enabled setting.
     */
    private static function getCacheEnabled(): array
    {
        return [
            'id' => EnableCache::getName(),
            'title' => Translator::translate(phraseId: 'cache-enabled'),
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'default' => EnableCache::getDefault(),
            'desc_tip' => 'Default: ' . EnableCache::getDefault(),
        ];
    }

    /**
     * Return array for Invalidate Cache button setting.
     *
     * @noinspection SpellCheckingInspection
     */
    private static function getInvalidateCacheButton(): array
    {
        return [
            'id' => Option::NAME_PREFIX . 'invalidate_cache',
            'title' => Translator::translate(phraseId: 'clear-cache'),
            'type' => 'rbinvalidatecachebutton'
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
            'desc' => __('Yes'),
            'default' => EnableGetAddress::getData(),
            'desc_tip' => 'Only available in Sweden.<br>Default: ' . EnableGetAddress::getDefault(),
        ];
    }

    /**
     * Setting to force payment method sorting in checkout as we use uuids there
     * instead of the gateway itself.
     */
    private static function getForcePaymentMethodSortOrder(): array
    {
        return [
            'id' => ForcePaymentMethodSortOrder::getName(),
            'title' => 'Sort payment methods according to admin',
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'default' => ForcePaymentMethodSortOrder::getDefault(),
            'desc_tip' => 'Default: ' . ForcePaymentMethodSortOrder::getDefault(),
        ];
    }

    /**
     * Make payment methods disappear when not in correct country.
     */
    private static function setMethodCountryRestriction(): array
    {
        return [
            'id' => SetMethodCountryRestriction::getName(),
            'title' => 'Restrict payment methods display in checkout to API country',
            'type' => 'checkbox',
            'desc' => __('Yes'),
            'default' => SetMethodCountryRestriction::getDefault(),
            'desc_tip' => 'Default: ' . SetMethodCountryRestriction::getDefault(),
        ];
    }

    /**
     * Timeout settings for API requests.
     */
    private static function getApiTimeout(): array
    {
        return [
            'id' => ApiTimeout::getName(),
            'type' => 'text',
            'title' => 'API Timeout (in seconds)',
            'default' => ApiTimeout::getDefault(),
            'desc' => 'Default: ' . ApiTimeout::getDefault(),
        ];
    }

    /**
     * Enabling xdebug where xdebug are usually hard to reach (like callbacks and other backend sessions).
     */
    private static function getXDebugSessionValue(): array
    {
        return [
            'id' => XDebugSessionValue::getName(),
            'title' => Translator::translate(phraseId: 'xdebug-session-value'),
            'type' => 'text',
            'default' => XDebugSessionValue::getDefault(),
            'desc' => Translator::translate(
                phraseId: 'enable-developer-mode-comment'
            ),
        ];
    }

    /**
     * @throws ConfigException
     */
    private static function getCountryRestrictionConfig(): array
    {
        // On new installs, countryCode tend to be empty until credentials are set.
        $countryCode = WooCommerce::getStoreCountry();
        return [
            'id' => 'get_address',
            'type' => 'text',
            'custom_attributes' => [
                'disabled' => true,
            ],
            'title' => Translator::translate(
                phraseId: 'enable-widget-to-get-address'
            ),
            'value' => __('Disabled'),
            'desc' => '<b>Not available in this country (' . $countryCode . ')</b>',
            // phpcs:ignore
            'css' => 'border: none; width: 100%; background: transparent; color: #000; box-shadow: none; font-weight: bold',
        ];
    }
}
