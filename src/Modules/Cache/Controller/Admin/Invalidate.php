<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache\Controller\Admin;

use Resursbank\Ecom\Config;
use Throwable;

/**
 * Invalidate cache store.
 */
class Invalidate
{
    /**
     * Invalidate cache store.
     *
     * @todo We should add success and error messages using new MessageBag when available. WOO-1040
     */
    public static function exec(): void
    {
        try {
            Config::getCache()->invalidate();

            // @todo Add success message when message bag has been implemented.
//            $msg = Translator::translate(phraseId: 'cache-cleared');
        } catch (Throwable $e) {
            // @todo Add error message when MessageBag has been implemented.
//            $msg = 'Failed to invalidate cache.';

            try {
                Config::getLogger()->error(message: $e);
            } catch (Throwable) {
                // @todo Add error message when MessageBag has been implemented.
//                $msg .= ' Failed to log error. Config::setup not working?';
            }
        }
    }
}
