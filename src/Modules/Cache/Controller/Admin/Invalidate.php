<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache\Controller\Admin;

use Resursbank\Ecom\Config;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Settings\Advanced;
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
            // Settings are automatically generated "per payment", but the callback url settings are saved due to
            // how the configuration in WooCommerce works. To make all right again, the values should be removed
            // if not automatically, but through the cache-cleaner.
            // @todo Try not to store in db since the data is not fetched from there anyway.
            delete_option(option: Advanced::NAME_PREFIX . 'authorization_callback_url');
            delete_option(option: Advanced::NAME_PREFIX . 'management_callback_url');

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
