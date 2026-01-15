<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Ecom\Module\UserSettings\Repository;

use function is_string;

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
     * Resolve payment method icon SVG based on type.
     */
    public static function getPaymentMethodIconUrl(
        Type $type
    ): string {
        $relativePath = 'lib/ecom/' . $type->getIconRelativePath();
        return plugin_dir_url(RESURSBANK_MODULE_DIR_PATH . '/' . $relativePath) . $type->getIconFilename();
    }

    /**
     * Generate a URL for a given endpoint, with a list of arguments.
     *
     * @param string $baseUrl
     * @param array $arguments
     * @return string
     * @throws IllegalValueException
     * @throws UserSettingsException
     * @todo Exceptions from this method will crash admin (probably frontend too). Not sure if that's the correct behavior?
     */
    public static function getQueryArg(string $baseUrl, array $arguments): string
    {
        $queryArgument = $baseUrl;
        $xdebugValue = Repository::getSettings()->xdebugSessionValue;

        if ($xdebugValue !== null && $xdebugValue !== '') {
            $arguments['XDEBUG_SESSION'] = $xdebugValue;
        }

        foreach ($arguments as $argumentKey => $argumentValue) {
            // Skip null values.
            if ($argumentValue === null) {
                continue;
            }

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
}
