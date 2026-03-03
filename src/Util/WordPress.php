<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

// Prevent direct access.
use Resursbank\Ecom\Lib\Api\Environment;
use ValueError;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress-specific utility helpers.
 */
class WordPress
{
    /**
     * Cached JSON request payload (read once per request).
     */
    private static ?array $jsonPayload = null;

    /**
     * Flag to track if we've attempted to read JSON.
     */
    private static bool $jsonPayloadRead = false;

    /**
     * Ensure pluggable functions are available as early as possible.
     */
    public static function ensurePluggableLoaded(): void
    {
        if (function_exists('wp_verify_nonce')) {
            return;
        }

        if (!defined('ABSPATH')) {
            return;
        }

        $pluggable = ABSPATH . WPINC . '/pluggable.php';

        if (!file_exists($pluggable)) {
            return;
        }

        include_once $pluggable;
    }

    /**
     * Verify a nonce value in a safe, centralized way.
     */
    public static function verifyNonce(string $nonce, string $action): bool
    {
        self::ensurePluggableLoaded();

        if (!function_exists('wp_verify_nonce')) {
            return false;
        }

        return (bool)wp_verify_nonce($nonce, $action);
    }

    /**
     * Verify a nonce from POST payload.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function verifyPostNonce(string $action, string $field = '_wpnonce'): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this IS the nonce verification function
        if (!isset($_POST[$field]) || !is_string(value: $_POST[$field])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this IS the nonce verification function
        $nonce = sanitize_text_field(wp_unslash($_POST[$field]));

        return self::verifyNonce($nonce, $action);
    }

    /**
     * Verify a nonce from decoded JSON payload.
     */
    public static function verifyJsonNonce(array $payload, string $action, string $field = 'nonce'): bool
    {
        if (!isset($payload[$field]) || !is_string(value: $payload[$field])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($payload[$field]));

        return self::verifyNonce($nonce, $action);
    }

    /**
     * Get and sanitize a query string parameter.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getQueryParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- caller must verify nonce when required
        if (!isset($_GET[$key]) || !is_string(value: $_GET[$key])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- caller must verify nonce when required
        return sanitize_text_field(wp_unslash($_GET[$key]));
    }

    /**
     * Get and sanitize a POST parameter.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getPostParam(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller must verify nonce when required
        if (!isset($_POST[$key]) || !is_string(value: $_POST[$key])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller must verify nonce when required
        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    /**
     * Get the decoded JSON payload from request body (cached after first read).
     *
     * Note: php://input can only be read once per request, so we cache it.
     * Returns an empty array if no JSON payload exists or if decoding fails.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getJsonPayload(): array
    {
        // Return cached payload if already read
        if (self::$jsonPayloadRead) {
            return self::$jsonPayload ?? [];
        }

        // Mark as read to prevent re-reading
        self::$jsonPayloadRead = true;

        // Read raw input from request body
        $rawInput = file_get_contents('php://input');

        if ($rawInput === false || $rawInput === '') {
            self::$jsonPayload = [];
            return [];
        }

        // Decode JSON
        $decoded = json_decode($rawInput, true);

        if (!is_array($decoded)) {
            self::$jsonPayload = [];
            return [];
        }

        self::$jsonPayload = $decoded;
        return $decoded;
    }

    /**
     * Get a specific value from the JSON payload with optional sanitization.
     *
     * @param string $key The key to retrieve from the JSON payload
     * @param bool $sanitize Whether to sanitize the value (default: true)
     * @return string|null The value as string if exists and is string, null otherwise
     */
    public static function getJsonParam(string $key, bool $sanitize = true): ?string
    {
        $payload = self::getJsonPayload();

        if (!isset($payload[$key]) || !is_string($payload[$key])) {
            return null;
        }

        $value = $payload[$key];

        return $sanitize ? sanitize_text_field($value) : $value;
    }

    /**
     * Check if current request is an admin AJAX request for a specific route.
     *
     * @param string $route The route to check for (e.g., 'get-stores-admin')
     * @return bool True if this is an admin AJAX request for the specified route
     */
    public static function isAdminAjaxRoute(string $route): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
            return false;
        }

        return self::getQueryParam(key: 'resursbank') === $route;
    }

    /**
     * Resolve environment value from admin AJAX request payload.
     *
     * Specifically handles the "get-stores-admin" request, where credentials
     * and environment are submitted as a JSON payload rather than form data.
     *
     * Uses centralized getJsonPayload() to retrieve cached JSON request body,
     * ensuring consistency across multiple access points.
     *
     * @return string|null The environment value from JSON payload, or null if unavailable.
     */
    public static function getEnvironmentFromAdminAjax(): ?string
    {
        $key = defined('RESURSBANK_MODULE_PREFIX')
            ? RESURSBANK_MODULE_PREFIX . '_environment'
            : 'resursbank_environment';

        // Get cached JSON payload (same instance used in Connection::getJwtFromPost)
        $payload = self::getJsonPayload();

        if (empty($payload)) {
            $postEnv = self::getPostParam($key);

            if ($postEnv === '') {
                return null;
            }

            try {
                return Environment::from(value: $postEnv)->value;
            } catch (ValueError) {
                return null;
            }
        }

        if (
            !self::verifyJsonNonce(
                payload: $payload,
                action: 'resursbank_get_stores_admin'
            )
        ) {
            return null;
        }

        // Extract environment from JSON payload
        $environment = self::getJsonParam('environment');

        if ($environment === null || $environment === '') {
            return null;
        }

        // Validate environment value
        try {
            $envEnum = Environment::from(value: $environment);
            return $envEnum->value;
        } catch (ValueError) {
            return null;
        }
    }
}
