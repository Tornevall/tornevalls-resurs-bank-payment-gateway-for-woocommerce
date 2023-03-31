<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use RuntimeException;

use function is_string;
use function strlen;

/**
 * URL related helper methods.
 */
class Url
{
    /**
     * Helper to get script file from sub-module resource directory.
     *
     * @param string $file | File path relative to resources dir.
     */
    public static function getScriptUrl(
        string $module,
        string $file
    ): string {
        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        // NOTE: plugin_dir_url returns everything up to the last slash.
        return plugin_dir_url(
            RESURSBANK_MODULE_DIR_NAME . "/src/Modules/$module/resources/js/" .
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
     * Resolve payment method icon SVG based on type.
     */
    public static function getPaymentMethodIconUrl(
        Type $type
    ): string {
        $file = match ($type) {
            Type::DEBIT_CARD, Type::CREDIT_CARD, Type::CARD => 'card.svg',
            Type::SWISH => 'swish.png',
            Type::INTERNET => 'trustly.svg',
            default => 'resurs.png'
        };

        return self::getEcomUrl(
            path: "src/Module/PaymentMethod/Widget/Resources/Images/$file"
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
        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        $result = plugin_dir_url($path) . $file;

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
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getHttpGet(string $key): ?string
    {
        return isset($_GET[$key]) && is_string(value: $_GET[$key])
            ? $_GET[$key]
            : null;
    }

    /**
     * Generate URL for MAPI callbacks.
     *
     * @throws IllegalValueException
     */
    public static function getCallbackUrl(CallbackType $type): string
    {
        return self::getQueryArg(
            baseUrl: WC()->api_request_url(request: Route::ROUTE_PARAM),
            arguments: [
                'callback' => $type->value,
            ]
        );
    }
}
