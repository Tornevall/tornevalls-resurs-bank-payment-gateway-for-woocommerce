<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Setting to enable get address widget.
 */
class EnableGetAddress extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'get_address_enabled';
    }

    public static function isEnabled(): bool
    {
        return self::getData() === 'yes';
    }
}
