<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Database\Options\Api;

use Resursbankabpayments\Woocommerce\Database\DataType\StringOption;
use Resursbankabpayments\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_client_id value in options table.
 */
class ClientId extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'client_id';
    }
}
