<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\PartPayment;

use Resursbank\Woocommerce\Database\DataType\IntOption;
use Resursbank\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_part_payment_limit value in options table.
 */
class Limit extends IntOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'part_payment_limit';
    }

    /**
     * Default value for part payment limit, when nothing is yet configured.
     */
    public static function getDefault(): ?string
    {
        $customerCountry = get_option('woocommerce_default_country');
        return $customerCountry === 'FI' ? '15' : '150';
    }
}
