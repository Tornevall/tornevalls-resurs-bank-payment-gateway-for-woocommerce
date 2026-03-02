<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Api;

use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\WordPress;
use ValueError;

class Environment extends Option implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'environment';
    }

    /**
     * Default environment value, used when no value is available from request or storage.
     */
    public static function getDefault(): ?string
    {
        return EnvironmentEnum::TEST->value;
    }

    /**
     * Resolve the API environment value with request-aware priority (since scope has been removed from ecom).
     *
     * Resolution order:
     * 1. Admin save POST (wc-save-section), before options are persisted.
     * 2. Admin AJAX request (get-stores-admin), using JSON request payload.
     * 3. Persisted option value from the database.
     *
     * This method exists to handle WordPress timing issues where option values
     * are not yet stored but must still be used for runtime configuration.
     *
     * @return string|null The resolved environment value, or null if unavailable.
     * @noinspection PhpMissingParentCallCommonInspection
     * @todo The configuration blobs for WC will disappear in future versions This is only a temporary solution.
     */
    public static function getRawData(): ?string
    {
        // Only attempt request-based resolution when nonce verification is available.
        if (function_exists('wp_verify_nonce')) {
            $fromRequest = self::getEnvironmentFromSavePost()
                ?? self::getEnvironmentFromAdminAjax();

            if ($fromRequest !== null) {
                return $fromRequest;
            }
        }

        return self::getEnvironmentFromOption();
    }

    /**
     * Get the resolved API environment as a typed enum.
     *
     * Converts the resolved raw environment value into an Environment enum.
     * Falls back to the default environment if no value is available.
     *
     * @throws ValueError If the resolved value cannot be mapped to a valid enum.
     */
    public static function getData(): EnvironmentEnum
    {
        return EnvironmentEnum::from(
            value: self::getRawData() ?? self::getDefault()
        );
    }

    /**
     * Resolve environment value from admin "Save changes" POST request.
     *
     * This is used during wc-save-section requests where options have not yet
     * been persisted to the database, but the submitted values must still be
     * respected for immediate API initialization.
     *
     * @return string|null The environment value from POST, or null if not applicable.
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private static function getEnvironmentFromSavePost(): ?string
    {
        $key = self::NAME_PREFIX . 'environment';

        if (!Admin::isAdmin()) {
            return null;
        }

        if (!WordPress::verifyPostNonce(action: 'woocommerce-settings')) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
        if (isset($_POST[$key]) && is_string(value: $_POST[$key])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
            $rawValue = sanitize_text_field(wp_unslash($_POST[$key]));

            if ($rawValue !== '') {
                try {
                    return EnvironmentEnum::from(value: $rawValue)->value;
                } catch (ValueError) {
                    // Ignore invalid enum values and continue resolution.
                }
            }
        }

        return null;
    }

    /**
     * Resolve environment value from admin AJAX request payload.
     *
     * Delegates to centralized WordPress::getEnvironmentFromAdminAjax() to avoid
     * code duplication and ensure consistent handling of JSON payloads.
     *
     * @return string|null The environment value from JSON payload, or null if unavailable.
     */
    private static function getEnvironmentFromAdminAjax(): ?string
    {
        return WordPress::getEnvironmentFromAdminAjax();
    }

    /**
     * Resolve environment value from the persisted options table.
     *
     * This is the final fallback used during normal execution when no
     * request-specific overrides are present.
     *
     * @return string|null The stored environment value, or null if not set.
     */
    private static function getEnvironmentFromOption(): ?string
    {
        $value = parent::getRawData();

        return is_string(value: $value) ? $value : null;
    }
}
