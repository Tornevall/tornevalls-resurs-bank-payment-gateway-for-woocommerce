<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Ecom\Module\Widget\GetAddress\Css as Widget;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

/**
 * Get basic CSS for the get address widget.
 */
class GetAddressCss
{
    public static function exec(): string
    {
        try {
            return (new Widget())->content;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return '';
    }
}
