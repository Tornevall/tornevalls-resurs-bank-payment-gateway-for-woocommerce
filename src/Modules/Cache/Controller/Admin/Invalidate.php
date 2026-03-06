<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\Cache\Controller\Admin;

use Resursbank\Ecom\Config;
use Resursbankabpayments\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbankabpayments\Woocommerce\Util\Log;
use Resursbankabpayments\Woocommerce\Util\Translator;
use Resursbankabpayments\Woocommerce\Util\WooCommerce;
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
            WooCommerce::invalidateFullCache();
            MessageBag::addSuccess(
                message: esc_html(
                    Translator::translate(phraseId: 'cache-cleared')
                )
            );
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: esc_html(
                    Translator::translate(phraseId: 'clear-cache-failed')
                )
            );
        }
    }
}
