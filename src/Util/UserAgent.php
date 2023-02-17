<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use ResursBank\Exception\WooCommerceNotActiveException;
use ResursBank\Service\WooCommerce;
use Throwable;

/**
 * Fetch UserAgent data from plugin registry.
 */
class UserAgent
{
    /**
     * Get version from the current installed plugin (which potentially can be dynamically installed
     * with different slugs).
     */
    public static function getPluginVersion(): string
    {
        // Using get_file_data here since WP's base function get_plugin_data is currently not available when
        // this method is called.
        $pluginFileData = get_file_data(
            file: RESURSBANK_GATEWAY_PATH . '/readme.txt',
            default_headers: ['plugin_version' => 'Stable tag']
        );

        return isset($pluginFileData['plugin_version']) &&
        is_string(value: $pluginFileData['plugin_version'])
            ? $pluginFileData['plugin_version']
            : '';
    }

    public static function getWooCommerceVersion(): string
    {
        return self::getVersionFromPluginData(
            pluginMatch: 'WooCommerce',
            pluginData: self::getWooCommerceInformation()
        );
    }

    /**
     * Generate a user agent string from internal components in WP.
     *
     * @throws WooCommerceNotActiveException
     */
    public static function getUserAgent(): string
    {
        // Making sure that we don't communicate without unavailable plugins.
        if (!WooCommerce::getActiveState()) {
            throw new WooCommerceNotActiveException(
                message: 'WooCommerce is not active or installed.'
            );
        }

        try {
            // Only data required from this point is our own. ECom shows the rest. The only data not currently
            // showing is the FQN, but should preferably be shown from ECom.
            $renderedVersionString = [
                'WooCommerce-' . self::getWooCommerceVersion(),
                'Resurs-' . self::getPluginVersion(),
            ];

            // Returned in standardized user-agent format.
            return implode(separator: ' +', array: $renderedVersionString);
        } catch (Throwable) {
            // Fail silently, but with at least a source indicator.
            return 'ResursBank-MAPI/WooCommerce';
        }
    }

    /**
     * @return array
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
                    path: plugin_basename(file: $pluginInit)
                ) !== 'woocommerce'
            ) {
                continue;
            }

            $return = get_plugin_data(plugin_file: $pluginInit);
        }

        return is_array(value: $return) ? $return : [];
    }

    /**
     * Extract data from plugin registry naturally but validated.
     *
     * @param string $pluginMatch Case-sensitive matching.
     * @param array $pluginData
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getVersionFromPluginData(string $pluginMatch, array $pluginData): string
    {
        return isset($pluginData['Name'], $pluginData['Version']) &&
        $pluginData['Name'] === $pluginMatch &&
        is_string(value: $pluginData['Version']) ? $pluginData['Version'] : '';
    }
}
