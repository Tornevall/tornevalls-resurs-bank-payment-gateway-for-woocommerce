<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag\Filter;


use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;

/**
 * Append messages to notices in WP admin.
 */
class AddNotices
{
    /**
     * Register action filter which executed when order item gets deleted.
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'admin_notices',
            callback: static function(): void {
                self::printErrors();
            }
        );
    }

    /**
     * Print collected errors.
     */
    private static function printErrors(): void
    {
        if (!(bool) is_admin()) {
            return;
        }

        foreach (MessageBag::getErrors() as $msg) {
            echo (
                '<div class="error notice"><p>'
                . esc_html(text: $msg)
                . '</p></div>'
            );
        }
    }
}
