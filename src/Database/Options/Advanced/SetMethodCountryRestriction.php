<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\BoolOption;
use Resursbank\Woocommerce\Database\OptionInterface;

/**
 * Implements border restrictions for payment methods.
 */
class SetMethodCountryRestriction extends BoolOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'set_method_country_restriction';
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): ?string
    {
        return 'no';
    }
}
