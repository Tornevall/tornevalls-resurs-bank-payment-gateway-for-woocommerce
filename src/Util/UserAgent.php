<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Throwable;

/**
 * Fetch UserAgent data from plugin registry.
 */
class UserAgent
{
    /**
     * Cached plugin version (resolved once per request).
     */
    private static string $pluginVersion = '1.0.0';

    /**
     * Whether version has been resolved yet.
     */
    private static bool $pluginVersionResolved = false;

    /**
     * Resolve plugin version from plugin metadata, cached for the request lifetime.
     *
     * Reads `Version:` from init.php first, falls back to `Stable tag:` in readme.txt.
     * Returns '1.0.0' if neither source is available.
     */
    public static function getPluginVersion(): string
    {
        if (self::$pluginVersionResolved) {
            return self::$pluginVersion;
        }

        self::$pluginVersionResolved = true;

        $root = self::getPluginRootPath();

        $version = self::readHeaderValue(
            filePath: $root . '/init.php',
            pattern: '/^\s*\*\s*Version:\s*(.+)$/mi'
        );

        if ($version === '') {
            $version = self::readHeaderValue(
                filePath: $root . '/readme.txt',
                pattern: '/^\s*Stable tag:\s*(.+)$/mi'
            );
        }

        if ($version !== '') {
            self::$pluginVersion = $version;
        }

        return self::$pluginVersion;
    }

    /**
     * Resolve version of WooCommerce.
     *
     * Note that we cannot use get_plugin_data() because that function depends
     * on WP functionality not yet loaded when we need to resolve this value.
     * This is because we need this data when we initialize Ecom, which we do as
     * early as possible. If you attempt to use get_plugin_data() you will get a
     * PHP notice.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public static function getWooCommerceVersion(): string
    {
        $plugin_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

        // Check if the file exists.
        if (!file_exists(filename: $plugin_file)) {
            return '';
        }

        // Read the file contents.
        $file_contents = file_get_contents(filename: $plugin_file);

        // Use a regular expression to extract the version information.
        $matches = [];

        if (
            preg_match(
                pattern: '/Version:\s*(\S+)/',
                subject: $file_contents,
                matches: $matches
            ) && isset($matches[1])
        ) {
            return $matches[1];
        }

        // Return default value.
        return 'Unknown';
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
                'WordPress-' . get_bloginfo('version')
            ]);
        } catch (Throwable) {
            // Fail silently, but with at least a source indicator.
            $return = 'ResursBank-MAPI/WooCommerce';
        }

        return $return;
    }

    /**
     * Resolve plugin root path from constant or relative to this file.
     */
    private static function getPluginRootPath(): string
    {
        if (defined('RESURSBANKABPAYMENTS_MODULE_DIR_PATH')) {
            return rtrim(
                string: RESURSBANKABPAYMENTS_MODULE_DIR_PATH,
                characters: '/'
            );
        }

        return dirname(path: __DIR__, levels: 2);
    }

    /**
     * Extract a single header value from a file using a regex pattern.
     */
    private static function readHeaderValue(string $filePath, string $pattern): string
    {
        if (!file_exists(filename: $filePath)) {
            return '';
        }

        $content = file_get_contents(filename: $filePath);

        if (!is_string(value: $content) || $content === '') {
            return '';
        }

        $matches = [];

        if (
            !preg_match(
                pattern: $pattern,
                subject: $content,
                matches: $matches
            ) ||
            !isset($matches[1])
        ) {
            return '';
        }

        return trim(string: $matches[1]);
    }
}
