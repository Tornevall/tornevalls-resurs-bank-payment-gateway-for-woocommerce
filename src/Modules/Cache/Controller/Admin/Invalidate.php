<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache\Controller\Admin;

use Resursbank\Ecom\Config;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Invalidate cache store.
 */
class Invalidate
{
    /**
     * Invalidate cache store.
     */
    public static function exec(): void
    {
        try {
            Config::getCache()->invalidate();
            MessageBag::addSuccess(
                message: Translator::translate(phraseId: 'cache-cleared')
            );
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: Translator::translate(phraseId: 'clear-cache-failed')
            );
        }
    }
}
