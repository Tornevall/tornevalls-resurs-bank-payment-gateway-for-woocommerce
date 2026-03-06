<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Database\Options\Advanced;

use Resursbankabpayments\Woocommerce\Database\DataType\StringOption;
use Resursbankabpayments\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_logs_enabled value in options table.
 */
class ApiTimeout extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'api_timeout';
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): ?string
    {
        return '30';
    }
}
