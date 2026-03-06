<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Database\Options\Callback;

use Resursbankabpayments\Woocommerce\Database\DataType\IntOption;
use Resursbankabpayments\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_callback_test value in options table.
 */
class TestReceivedAt extends IntOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'callback_test_received_at';
    }
}
