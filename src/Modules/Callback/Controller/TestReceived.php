<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\Callback\Controller;

use Resursbankabpayments\Woocommerce\Database\Options\Callback\TestReceivedAt;
use Resursbankabpayments\Woocommerce\Util\Log;
use Throwable;

/**
 * Write timestamp to database, confirming test callback came through.
 */
class TestReceived
{
    public static function exec(): void
    {
        try {
            TestReceivedAt::setData(value: (string) time());
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }
}
