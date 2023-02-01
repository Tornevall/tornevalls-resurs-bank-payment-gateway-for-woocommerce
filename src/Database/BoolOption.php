<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

/**
 * Append additional method to StringOption to convert value to bool.
 */
class BoolOption extends StringOption
{
    /**
     * Booleans are stored in wp_options as 'yes' / 'no'.
     */
    public static function isEnabled(): bool
    {
        return self::getData() === 'yes';
    }
}
