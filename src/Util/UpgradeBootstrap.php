<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles one-time bootstrap skip after plugin upgrade.
 */
final class UpgradeBootstrap
{
    private const TRANSIENT_SKIP_BOOTSTRAP_ONCE = 'resursbank_skip_bootstrap_once';

    /**
     * Skip a single bootstrap cycle immediately after plugin update.
     */
    public static function shouldSkipBootstrapOnce(): bool
    {
        if (!get_transient(self::TRANSIENT_SKIP_BOOTSTRAP_ONCE)) {
            return false;
        }

        delete_transient(self::TRANSIENT_SKIP_BOOTSTRAP_ONCE);

        return true;
    }

    /**
     * Register update signal hook that marks next request for bootstrap skip.
     */
    public static function registerUpgradeSignalHook(string $pluginBasename): void
    {
        add_action(
            'upgrader_process_complete',
            static function (mixed $upgrader, array $hookExtra) use ($pluginBasename): void {
                if (
                    ($hookExtra['type'] ?? '') !== 'plugin' ||
                    ($hookExtra['action'] ?? '') !== 'update'
                ) {
                    return;
                }

                $plugins = $hookExtra['plugins'] ?? [];

                if (!is_array($plugins)) {
                    return;
                }

                if (!in_array($pluginBasename, $plugins, true)) {
                    return;
                }

                set_transient(
                    self::TRANSIENT_SKIP_BOOTSTRAP_ONCE,
                    1,
                    MINUTE_IN_SECONDS
                );
            },
            10,
            2
        );
    }
}
