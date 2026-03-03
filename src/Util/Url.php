<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use JsonException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Woocommerce\Database\Options\Advanced\XDebugSessionValue;
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
    public static function getResourceUrl(
        string $module,
        string $file,
        ResourceType $type = ResourceType::JS
    ): string {
        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        // NOTE: plugin_dir_url returns everything up to the last slash.
        return plugin_dir_url(
            RESURSBANK_MODULE_DIR_NAME . "/src/Modules/$module/resources/$type->value/" .
                str_replace(search: '/', replace: '', subject: $file)
        ) . $file;
    }

    /**
     * Helper to get script file from assets directory.
     *
     * @param string $file | File path relative to resources dir.
     */
    public static function getAssetUrl(
        string $file
    ): string {
        return plugin_dir_url(
            file: RESURSBANK_MODULE_DIR_NAME . '/assets/js/dist/' .
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
            path: RESURSBANK_MODULE_DIR_NAME . "/vendor/ecom/$path"
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

        return self::getEcomUrl(path: "src/Module/Widget/Logo/img/$file");
    }

    /**
     * Generate a URL for a given endpoint, with a list of arguments.
     *
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getQueryArg(string $baseUrl, array $arguments): string
    {
        $queryArgument = $baseUrl;

        if (XDebugSessionValue::getData() !== '') {
            $arguments['XDEBUG_SESSION'] = XDebugSessionValue::getData();
        }

        foreach ($arguments as $argumentKey => $argumentValue) {
            if (!is_string(value: $argumentValue)) {
                throw new IllegalValueException(
                    message: sprintf(
                        'Argument value is not a string (type: %s).',
                        esc_html(gettype(value: $argumentValue))
                    )
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
            (string)substr(string: $path, offset: $offset + 1) : '';

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
                message: sprintf(
                    'Could not produce a string URL for "%s". Result came back as: %s',
                    esc_html($path),
                    esc_html(gettype(value: $result))
                )
            );
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getHttpGet(string $key): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- caller must verify nonce when required
        if (!isset($_GET[$key]) || !is_string(value: $_GET[$key])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- caller must verify nonce when required
        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    /**
     * Request similar to _GET, but for _POST (we won't handle _REQUEST).
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getHttpPost(string $key): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller must verify nonce when required
        if (!isset($_POST[$key]) || !is_string(value: $_POST[$key])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller must verify nonce when required
        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    /**
     * Get JSON requests the same way we do for _GET and _POST.
     *
     * Sanitizes the decoded JSON values to prevent injection of unsanitized data.
     *
     * @throws JsonException
     */
    public static function getHttpJson(string $key): null|string|int|float
    {
        $jsonData = file_get_contents(filename: 'php://input');
        $data = json_decode(
            json: $jsonData,
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );

        if (!isset($data[$key]) || !$data[$key]) {
            return null;
        }

        $value = $data[$key];

        // Sanitize the decoded value based on its type
        if (is_string($value)) {
            return sanitize_text_field(wp_unslash($value));
        }

        if (is_numeric($value)) {
            // For numeric values, cast appropriately
            if (is_int($value)) {
                return absint($value);
            }

            return (float)$value;
        }

        return $value;
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
