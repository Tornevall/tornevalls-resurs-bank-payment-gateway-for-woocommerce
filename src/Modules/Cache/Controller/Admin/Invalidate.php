<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache\Controller\Admin;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
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
                msg: Translator::translate(phraseId: 'cache-cleared')
            );
        } catch (Throwable $e) {
            // @todo This should be phrased through ECom, but we should avoid all Exceptions here.
            MessageBag::addError(msg: 'Failed to clear cache.');

            Log::error(error: $e);
        }
    }
}
