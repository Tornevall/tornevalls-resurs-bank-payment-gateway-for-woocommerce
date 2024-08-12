<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Throwable;

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
     * HPOS compatible method to find out if current screen is shop_order (wp-admin order view).
     */
    public static function isInShopOrder(): bool
    {
        return get_current_screen()->id === 'shop_order' || get_current_screen()->post_type === 'shop_order';
    }

    /**
     * Return boolean on specific admin configuration tab. This method does not check is_admin first.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isTab(string $tabName): bool
    {
        return isset($_GET['tab'], $_GET['page']) &&
            $_GET['page'] === 'wc-settings' &&
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
                isset($_GET['section']) &&
                $_GET['section'] === $sectionName
            ) {
                $return = true;
            } elseif ($sectionName === '' && !isset($_GET['section'])) {
                // If requested section is empty and no section is requested, allow true booleans too.
                $return = true;
            }
        }

        return $return;
    }

    /**
     * HPOS compatible method to find out if current screen is shop_order (wp-admin order view).
     */
    public static function isInShopOrder(): bool
    {
        // id is used in legacy mode. post_type is used in HPOS mode.
        return get_current_screen()->id === 'shop_order' || get_current_screen()->post_type === 'shop_order';
    }

    /**
     * Check if user is currently located in the order list.
     */
    public static function isInOrderListView(): bool
    {
        // The list screen is held separately from the single order view and is regardless of HPOS
        // always the id.
        return self::isInShopOrder() && get_current_screen()->id === 'edit-shop_order';
    }
}
