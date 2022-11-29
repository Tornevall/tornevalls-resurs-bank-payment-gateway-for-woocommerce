<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Database interface for client_secret in wp_options table.
 *
 * NOTE: We actively avoid validating this value to avoid exploits based on
 * validation errors.
 */
class ClientSecret extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'client_secret';
    }
}
