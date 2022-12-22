<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Exception;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
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
     * @param string $file | File path relative to resources dir.
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
     * @param array $arguments
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

    /**
     * Handle $_REQUEST, etc naturally, together with WP/WC-request standards.
     * Used to help suppressing usages of super-globals.
     *
     * @param array|null $httpPostData Data to fetch from if not $_REQUEST, for example _POST or _GET can be put here.
     * @throws Exception
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getRequest(string $key, ?array $httpPostData = null): mixed
    {
        // If $httpPostData has content, use this instead of default $_REQUEST.
        $requestArray = is_array(value: $httpPostData)
            ? $httpPostData
            : $_REQUEST;
        // Sanitize all requests before returning it.
        $request = self::getSanitizedArray(array: $requestArray);
        $return = $request[$key] ?? null;
        // Handle post data from WP/WC post requests (which normally occurs in checkout phases).
        // Data that are handled from WC-checkouts are normally posted throught $_REQUEST['post_data']. If this
        // happens and the getRequest have on return value available at this post, we will start checking the
        // post_data-content here.
        $wpPostData = isset($_REQUEST['post_data']) ?? null;

        if (
            $return === null &&
            isset($wpPostData) &&
            is_string(value: $wpPostData)
        ) {
            parse_str($wpPostData, $newPostData);
            // Early sanitizing should not apply here, but in the ending late handling (WP rules).
            // Applying the values wrong could also cause strange output.
            $return = $newPostData[$key] ?? '';
        }

        return $return;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getHttpGet(string $key): ?string
    {
        return isset($_GET[$key]) && is_string($_GET[$key])
            ? $_GET[$key]
            : null;
    }

    /**
     * Recursive string-in-array sanitizer (for both associative and non-associative arrays).
     * Based on WP sanitizing rules.
     *
     * @param array $array Data not only from the plugin lands here.
     * @return array
     * @throws Exception
     */
    public static function getSanitizedArray(array $array): array
    {
        $arrays = new ArrayValidation();
        $returnArray = [];

        try {
            $arrays->isAssoc(data: $array);

            foreach ($array as $arrayKey => $arrayValue) {
                $stringKeyElement = (string)$arrayKey;

                if (is_array(value: $arrayValue)) {
                    // Recursive request.
                    $returnArray[self::getSanitizedKeyElement(
                        key: $stringKeyElement
                    )] =
                        self::getSanitizedArray(array: $arrayValue);
                } elseif (!is_object(value: $arrayValue)) {
                    $returnArray[self::getSanitizedKeyElement(
                        key: $stringKeyElement
                    )] = esc_html(text: $arrayValue);
                }
            }
        } catch (IllegalValueException) {
            // When not associative.
            foreach ($array as $item) {
                if (is_array(value: $item)) {
                    // Recursive request.
                    $returnArray[] = self::getSanitizedArray(array: $item);
                } elseif (is_string(value: $item)) {
                    $returnArray[] = esc_html(text: $item);
                }
            }
        }

        return $returnArray;
    }

    /**
     * Case fixed sanitizer, cloned from WordPress own functions, for which we sanitize keys based on
     * element id's. Resurs Bank is very much built on case-sensitive values for why we want to sanitize
     * on both lower- and uppercase.
     *
     * @throws Exception
     */
    public static function getSanitizedKeyElement(string $key): string
    {
        $sanitizedKey = preg_replace(
            pattern: '/[^a-z0-9_\-]/i',
            replacement: '',
            subject: $key
        );
        // Letting WordPress do their thing.
        $return = apply_filters(
            hook_name: 'sanitize_key',
            value: $sanitizedKey,
            args: $key
        );

        if (!is_string(value: $return)) {
            throw new Exception(
                message: 'Sanitized key element is no longer a string.'
            );
        }

        return $return;
    }
}
