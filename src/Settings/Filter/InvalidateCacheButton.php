<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings\Filter;

use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Sanitize;
use Resursbank\Woocommerce\Util\Translator;
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
                try {
                    $url = Sanitize::sanitizeHtml(
                        html: Route::getUrl(
                            route: Route::ROUTE_ADMIN_CACHE_INVALIDATE,
                            admin: true
                        )
                    );
                    $title = Translator::translate(phraseId: 'clear-cache');

                    echo <<<EX
<tr>
  <th scope="row" class="titledesc" />
  <td class="forminp">
    <a class="button-primary" href="$url">$title</a>
  </td>
</tr>
EX;
                } catch (Throwable $e) {
                    // @todo Trails one page load, message bag already rendered.
                    Log::error(
                        error: $e,
                        msg: 'Failed rendering clear cache button. See log'
                    );
                }
            }
        );
    }
}