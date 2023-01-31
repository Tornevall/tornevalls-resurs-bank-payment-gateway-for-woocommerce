<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings\Filter;

use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Sanitize;
use Throwable;

/**
 * Filter (event listener) which adds custom button to invalidate cache store.
 */
class InvalidateCacheButton
{
    /**
     * Add event listener to render the custom button element.
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_admin_field_rbinvalidatecachebutton',
            callback: static function (): void {
                $url = Sanitize::sanitizeHtml(html: self::getUrl());
                $title = Sanitize::sanitizeHtml(html: self::getTitle());

                echo <<<EX
<tr>
  <th scope="row" class="titledesc" />
  <td class="forminp">
    <a class="button-primary" href="$url">$title</a>
  </td>
</tr>
EX;
            }
        );
    }

    /**
     * Return button title.
     */
    private static function getTitle(): string
    {
        try {
            return Translator::translate(phraseId: 'clear-cache');
        } catch (Throwable) {
            return 'Clear cache';
        }
    }

    /**
     * Return controller URL.
     */
    private static function getUrl(): string
    {
        try {
            return Route::getUrl(route: Route::ROUTE_ADMIN_CACHE_INVALIDATE);
        } catch (Throwable) {
            return 'Failed to generate button to clear cache store.';
        }
    }
}
