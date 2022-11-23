<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use RuntimeException;

/**
 * URL related helper methods.
 */
class Url
{
	public const NAMESPACE = 'resursbank';

	/**
	 * Helper to get script file from sub-module resource directory.
	 *
	 * @param string $module
	 * @param string $file | File path relative to resources dir.
	 * @return string
	 */
	public static function getScriptUrl(
		string $module,
		string $file
	): string {
		// NOTE: plugin_dir_url returns everything up to the last slash.
		return plugin_dir_url(
			file: RESURSBANK_MODULE_DIR_NAME . "/src/Modules/$module/resources/js/$file"
		) . $file;
	}

    /**
     * Returns URL for a "lib/ecom" file.
     *
     * @param string $path
     * @return string
     */
    public static function getEcomUrl(
        string $path
    ): string {
        return self::getUrl(path: RESURSBANK_MODULE_DIR_NAME . "/lib/ecom/$path");
    }

    /**
     * Returns the URL of the given path.
     *
     * @param string $path
     * @return string
     */
    public static function getUrl(
        string $path
    ): string {
        $file = (string) substr($path, strrpos($path, '/') + 1);

        if ($file === '') {
            if ($path !== '' && strrpos($path, '/') === strlen($path) - 1) {
                throw new RuntimeException(
                    message: 'The path may not end with a "/".'
                );
            }

            throw new RuntimeException(
                message: 'The path does not end with a file/directory name.'
            );
        }

        // NOTE: plugin_dir_url returns everything up to the last slash.
        return self::getPluginUrl($path, $file);
    }

    /**
     * Wrapper for `plugin_dir_url()` that ensures that we get a string back.
     *
     * @param string $path
     * @param string $file
     * @return string
     */
    public static function getPluginUrl(
        string $path,
        string $file
    ): string {
        $result = plugin_dir_url($path) . $file;

        if (!is_string($result)) {
            throw new RuntimeException(
                message: 'Could not produce a string URL for ' .
                "\"$path\". Result came back as: " . gettype($result)
            );
        }

        return $result;
    }
}
