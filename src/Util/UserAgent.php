<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Throwable;

/**
 * Fetch UserAgent data from plugin registry.
 */
class UserAgent
{
    /**
     * Get version from the current installed plugin (which potentially can be dynamically installed
     * with different slugs).
     *
     * @throws IllegalValueException
     */
    public static function getPluginVersion(): string
    {
        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        // Using get_file_data here since WordPress' base function get_plugin_data is currently not available when
        // this method is called.
        $pluginFileData = get_file_data(
            RESURSBANK_MODULE_DIR_PATH . '/readme.txt',
            ['plugin_version' => 'Stable tag']
        );

        if (
            (
                !isset($pluginFileData['plugin_version']) &&
                !is_string(value: $pluginFileData['plugin_version']) ||
                $pluginFileData['plugin_version'] === ''
            )
        ) {
            throw new IllegalValueException(
                message: 'Plugin version is missing.'
            );
        }

        return $pluginFileData['plugin_version'];
    }

    /**
     * Simplified method to fetch WooCommerce version from the current plugin data.
     */
    public static function getWooCommerceVersion(): string
    {
        return self::getVersionFromPluginData(
            pluginMatch: 'WooCommerce',
            pluginData: self::getWooCommerceInformation()
        );
    }

    /**
     * Generate a user agent string from internal components in WP.
     */
    public static function getUserAgent(): string
    {
        try {
            $return = implode(separator: ' +', array: [
                'WooCommerce-' . self::getWooCommerceVersion(),
                'Resurs-' . self::getPluginVersion(),
            ]);
        } catch (Throwable) {
            // Fail silently, but with at least a source indicator.
            $return = 'ResursBank-MAPI/WooCommerce';
        }

        return $return;
    }

    /**
     * Fetch WooCommerce version information via the available plugin.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function getWooCommerceInformation(): array
    {
        $return = [];

        if (!function_exists(function: 'get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginList = wp_get_active_and_valid_plugins();

        foreach ($pluginList as $pluginInit) {
            if (
                dirname(
                    path: plugin_basename($pluginInit)
                ) !== 'woocommerce'
            ) {
                continue;
            }

            $return = get_plugin_data($pluginInit);
        }

        return is_array(value: $return) ? $return : [];
    }

    /**
     * Extract data from plugin registry naturally but validated.
     *
     * @param string $pluginMatch Case-sensitive matching.
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getVersionFromPluginData(string $pluginMatch, array $pluginData): string
    {
        return isset($pluginData['Name'], $pluginData['Version']) &&
        $pluginData['Name'] === $pluginMatch &&
        is_string(value: $pluginData['Version']) ? $pluginData['Version'] : '';
    }
}
