<?php

/** @noinspection PhpArgumentWithoutNamedIdentifierInspection */

/**
 * Add admin notice when dependencies like ecom is missing.
 *
 * @return void
 */
function resursBankHasNoEcom(): void
{
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>ECom2:</strong> Dependencies are missing from the plugin structure. Please verify your Resurs installation.</p>';
        echo '</div>';
    });
}

/**
 * Add admin notice when installation runs on PHP older than 8.1.
 *
 * @return void
 */
function resursBankHasOldPhp(): void
{
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>ECom2:</strong> Your PHP version (' . PHP_VERSION . ') is too old. This plugin requires PHP 8.1.0 or higher. Please update your PHP version to continue using the Resurs plugin.</p>';
        echo '</div>';
    });
}
