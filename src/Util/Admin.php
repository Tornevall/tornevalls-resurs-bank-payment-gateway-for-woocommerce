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
     * Return boolean on is_admin and a specific configuration tab.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isTab(string $tabName): bool
    {
        return self::isAdmin() &&
            isset($_GET['tab']) &&
            isset($_GET['page']) &&
            $_GET['page'] === 'wc-settings' &&
            $_GET['tab'] === $tabName;
    }

    /**
     * Return boolean on is_admin, resurs-plugin-tab and a specific section name.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function isSection(string $sectionName): bool
    {
        $return = false;

        if (Admin::isTab(tabName: RESURSBANK_MODULE_PREFIX)) {
            if (
                isset($_GET['section']) &&
                $_GET['section'] === $sectionName
            ) {
                $return = true;
            } elseif ($sectionName === '' && !isset($_GET['section'])) {
                $return = true;
            }
        }

        return $return;
    }
}
