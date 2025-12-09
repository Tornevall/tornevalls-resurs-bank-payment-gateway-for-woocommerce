<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

/**
 * Possible message types.
 */
enum Type: string
{
    case ERROR = 'error';
    case SUCCESS = 'success';

    /**
     * Convert to WordPress admin notice type.
     */
    public function toWordPressType(): string
    {
        return match($this) {
            self::ERROR => 'error',
            self::SUCCESS => 'success',
        };
    }

    /**
     * Convert to WooCommerce notice type.
     */
    public function toWooCommerceType(): string
    {
        return match($this) {
            self::ERROR => 'error',
            self::SUCCESS => 'success',
        };
    }
}