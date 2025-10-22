<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback\Controller;

use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

/**
 * Write timestamp to database, confirming test callback came through.
 */
class TestReceived
{
    public static function exec(): void
    {
        try {
            update_option(
                option: Reader::getOptionName(field: Field::TEST_RECEIVED_AT),
                value: time()
            );
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }
}
