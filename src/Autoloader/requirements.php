<?php

declare(strict_types=1);

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin notice when dependencies like ecom are missing.
 */
function resursbankabpaygw_has_no_ecom(): void
{
    add_action('admin_notices', static function (): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML structure, no user input
        echo wp_kses_post(
            '<div class="notice notice-error is-dismissible">' .
            '<p><strong>ECom2:</strong> Dependencies are missing from the plugin structure. ' .
            'Please verify your Resurs installation.</p>' .
            '</div>'
        );
    });
}

/**
 * Add admin notice when installation runs on PHP older than 8.1.
 */
function resursbankabpaygw_has_old_php(): void
{
    add_action('admin_notices', static function (): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PHP_VERSION is from PHP core
        echo wp_kses_post(
            '<div class="notice notice-error is-dismissible">' .
            '<p><strong>ECom2:</strong> Your PHP version (' . esc_html(PHP_VERSION) . ') is too old. This plugin requires ' .
            'PHP 8.1.0 or higher. Please update your PHP version to continue using the Resurs plugin.</p>' .
            '</div>'
        );
    });
}
