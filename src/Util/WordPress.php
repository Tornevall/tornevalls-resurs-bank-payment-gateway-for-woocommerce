<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

// Prevent direct access.
if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

/**
 * WordPress-specific utility helpers.
 */
class WordPress
{
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
     */
    public static function verifyPostNonce(string $action, string $field = '_wpnonce'): bool
    {
        if (!isset($_POST[$field]) || !is_string(value: $_POST[$field])) {
            return false;
        }

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
     * Sanitize widget HTML while preserving required form markup.
     */
    public static function sanitizeWidgetHtml(string $html): string
    {
        return wp_kses(
            $html,
            [
                'div' => [
                    'id' => true,
                    'class' => true,
                    'style' => true,
                    'data-*' => true,
                    'aria-*' => true,
                    'role' => true,
                    'tabindex' => true
                ],
                'label' => [
                    'for' => true,
                    'class' => true,
                    'id' => true,
                    'data-*' => true,
                    'aria-*' => true
                ],
                'input' => [
                    'type' => true,
                    'id' => true,
                    'name' => true,
                    'value' => true,
                    'class' => true,
                    'checked' => true,
                    'data-*' => true,
                    'aria-*' => true
                ],
                'button' => [
                    'type' => true,
                    'id' => true,
                    'class' => true,
                    'data-*' => true,
                    'aria-*' => true,
                    'role' => true
                ],
                'span' => [
                    'class' => true,
                    'id' => true,
                    'data-*' => true,
                    'aria-*' => true
                ]
            ]
        );
    }

    /**
     * Sanitize payment methods widget HTML while preserving its table and styles.
     */
    public static function sanitizePaymentMethodsHtml(string $html): string
    {
        return wp_kses(
            $html,
            [
                'style' => [],
                'div' => [
                    'class' => true
                ],
                'table' => [],
                'thead' => [],
                'tbody' => [],
                'tr' => [
                    'id' => true
                ],
                'th' => [],
                'td' => [],
                'p' => [
                    'class' => true
                ]
            ]
        );
    }

    /**
     * Sanitize support info widget HTML while preserving its table markup.
     */
    public static function sanitizeSupportInfoHtml(string $html): string
    {
        return wp_kses(
            $html,
            [
                'div' => [
                    'class' => true
                ],
                'table' => [],
                'tbody' => [],
                'tr' => [],
                'td' => [
                    'class' => true
                ],
                'span' => [
                    'class' => true
                ],
                'br' => []
            ]
        );
    }

    /**
     * Get and sanitize a query string parameter.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getQueryParam(string $key): string
    {
        if (!isset($_GET[$key]) || !is_string(value: $_GET[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_GET[$key]));
    }
}
