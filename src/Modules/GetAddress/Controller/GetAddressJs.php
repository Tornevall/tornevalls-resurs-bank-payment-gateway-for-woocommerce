<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Woocommerce\Modules\GetAddress\GetAddress as GetAddressWidget;
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
            return GetAddressWidget::getWidget()->js;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return '';
    }
}
