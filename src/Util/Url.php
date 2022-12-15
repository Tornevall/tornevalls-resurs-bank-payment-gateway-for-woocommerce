<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use RuntimeException;

use function is_string;
use function strlen;

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
                file: RESURSBANK_MODULE_DIR_NAME . "/src/Modules/$module/resources/js/" .
                      str_replace(search: '/', replace: '', subject: $file)
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
        return self::getUrl(
            path: RESURSBANK_MODULE_DIR_NAME . "/lib/ecom/$path"
        );
    }

    /**
     * Generate a URL for a given endpoint, with a list of arguments.
     *
     * @param string $baseUrl
     * @param array $arguments
     * @return string
     * @throws IllegalValueException
     */
    public static function getQueryArg(string $baseUrl, array $arguments): string
    {
        $queryArgument = $baseUrl;

        foreach ($arguments as $argumentKey => $argumentValue) {
            if (!is_string(value: $argumentValue)) {
                throw new IllegalValueException(
                    message: "$argumentValue is not a string"
                );
            }

            /** @psalm-suppress MixedAssignment */
            $query = add_query_arg(
                $argumentKey,
                $argumentValue,
                $queryArgument
            );

            if (!is_string(value: $query)) {
                continue;
            }

            $queryArgument = $query;
        }

        return $queryArgument;
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
        $offset = strrpos(haystack: $path, needle: '/');

        /** @noinspection PhpCastIsUnnecessaryInspection */
        $file = $offset !== false ?
            (string) substr(string: $path, offset: $offset + 1) : '';

        if ($file === '') {
            if (
                $path !== '' &&
                strrpos(haystack: $path, needle: '/') === strlen(
                    string: $path
                ) - 1
            ) {
                throw new RuntimeException(
                    message: 'The path may not end with a "/".'
                );
            }

            throw new RuntimeException(
                message: 'The path does not end with a file/directory name.'
            );
        }

        // NOTE: plugin_dir_url returns everything up to the last slash.
        return self::getPluginUrl(path: $path, file: $file);
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
        $result = plugin_dir_url(file: $path) . $file;

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if (!is_string(value: $result)) {
            throw new RuntimeException(
                message: 'Could not produce a string URL for ' .
                "\"$path\". Result came back as: " . gettype(value: $result)
            );
        }

        return $result;
    }
}