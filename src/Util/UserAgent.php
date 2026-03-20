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
     * Get version from the current installed plugin (which potentially can be dynamically installed
     * with different slugs).
     */
    public static function getPluginVersion(): string
    {
        return WordPress::getPluginVersion();
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
}
