<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Ecom\Module\Widget\GetAddress\Js;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Throwable;

/**
 * Retrieve JS for the get address widget.
 */
class GetAddressJs
{
    public static function exec(): string
    {
        try {
            return (new Js(url: Route::getUrl(route: Route::ROUTE_GET_ADDRESS)))->content;
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        return '';
    }
}
