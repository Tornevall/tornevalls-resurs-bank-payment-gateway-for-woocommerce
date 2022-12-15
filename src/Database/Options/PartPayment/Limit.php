<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\PartPayment;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Setting for the duration to be used by the part payment widget.
 */
class Limit extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'partpayment_limit';
    }
}
