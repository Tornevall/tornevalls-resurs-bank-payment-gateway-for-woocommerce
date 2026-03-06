<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Database\Options\OrderManagement;

use Resursbankabpayments\Woocommerce\Database\DataType\BoolOption;
use Resursbankabpayments\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_enable_refund value in options table.
 */
class EnableRefund extends BoolOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'enable_refund';
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function getDefault(): ?string
    {
        return 'yes';
    }
}
