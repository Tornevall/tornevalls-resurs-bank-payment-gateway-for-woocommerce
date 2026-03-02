<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Validation\StringValidation;
use Throwable;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * General utility functionality for admin-side things
 */
class Admin
{
    /**
     * Wrapper for is_admin to ensure we never get exceptions/error thrown.
     */
    public static function isAdmin(): bool
    {
        try {
            return (bool)(is_admin() ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine if we are in a frontend rendering context.
     *
     * This method exists because WooCommerce may instantiate gateways in wp-admin
     * (e.g., for building lists, checking availability, fetching gateway data,
     * hooks, block/analytics/order views, etc.) even when no checkout is in progress.
     * Additionally, wp-admin requests may still appear to have an active cart
     * (via session state or HPOS), causing payment field and USP rendering to run
     * incorrectly.
     *
     * Standard signals like is_admin(), is_ajax(), and cart presence are unreliable
     * for this purpose. This method provides a consolidated check for all non-frontend
     * contexts: admin, REST, AJAX, and Resurs Bank callbacks.
     *
     * Use this as a guard before rendering checkout UI components (payment fields,
     * assets, USP text, etc. - especially when we are depending on USP and priceSignage)
     * to ensure they only run during actual frontend checkout.
     */
    public static function isFrontendContext(): bool
    {
        return !defined(constant_name: 'IS_RESURS_CALLBACK')
            && !is_admin()
            && !defined(constant_name: 'WP_ADMIN')
            && !defined(constant_name: 'REST_REQUEST')
            && !(defined(constant_name: 'DOING_AJAX') && DOING_AJAX);
    }

    public static function getAdminErrorNote(string $message, string $additional = ''): void
    {
        if (!self::isAdmin()) {
            return;
        }

        // Admin notices must be added via action hook. Not echo directly.
        add_action(
            'admin_notices',
            static function () use ($message, $additional): void {
                echo '<div class="notice notice-error">';
                echo wp_kses_post($message);

                if ($additional !== '') {
                    echo '<br />' . wp_kses_post($additional);
                }

                echo '</div>';
            }
        );
    }

    /**
     * Return boolean on specific admin configuration tab. This method does not check is_admin first.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isTab(string $tabName): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
        return isset($_GET['tab'], $_GET['page']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
            $_GET['page'] === 'wc-settings' &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
            $_GET['tab'] === $tabName;
    }

    /**
     * Return boolean when resurs-plugin-tab are requested. This method does not check is_admin first.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isSection(string $sectionName): bool
    {
        $return = false;

        if (
            Admin::isTab(tabName: RESURSBANK_MODULE_PREFIX) ||
            Admin::isTab(tabName: 'checkout')
        ) {
            if (
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
                isset($_GET['section']) &&
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
                $_GET['section'] === $sectionName
            ) {
                $return = true;
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check for rendering context
            } elseif ($sectionName === '' && !isset($_GET['section'])) {
                // If requested section is empty and no section is requested, allow true booleans too.
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Redirect to the correct section if the wrong section is requested.
     *
     * Wrong section for Resurs example: page=wc-settings&tab=checkout&section=<method-uuid>
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function redirectAtWrongSection(mixed $method): void
    {
        try {
            // Make sure we are dealing with a UUID, before activating the redirect filter to minimize the
            // risk of weird loops (IF they occur).
            $stringValidation = new StringValidation();
            $stringValidation->isUuid(value: $method);
        } catch (Throwable) {
            // Do nothing.
            return;
        }

        add_filter(
            'woocommerce_get_sections_checkout',
            static function (array $sections = []) use ($method): array {
                if (
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect safety check
                    isset($_REQUEST['section']) &&
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect safety check
                    $_REQUEST['section'] === $method
                ) {
                    wp_safe_redirect(
                        'admin.php?page=wc-settings&tab=resursbank&section=payment_methods'
                    );
                    wp_die();
                }

                return $sections;
            }
        );
    }

    /**
     * HPOS compatible method to find out if current screen is shop_order (wp-admin order view).
     */
    public static function isInShopOrderEdit(): bool
    {
        // Current screen can be null when is_ajax().
        $currentScreen = get_current_screen();
        // id is used in legacy mode. post_type is used in HPOS mode.
        return isset($currentScreen) &&
            ($currentScreen->id === 'shop_order' || $currentScreen->post_type === 'shop_order');
    }

    /**
     * Check if user is currently located in the order list.
     */
    public static function isInOrderListView(): bool
    {
        $currentScreen = get_current_screen();
        // The list screen is held separately from the single order view and is regardless of HPOS
        // always the id.
        return self::isInShopOrderEdit() && isset($currentScreen) && $currentScreen->id === 'edit-shop_order';
    }
}
