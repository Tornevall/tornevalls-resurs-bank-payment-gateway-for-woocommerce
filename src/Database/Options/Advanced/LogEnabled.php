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
 * Implementation of resursbank_logs_enabled value in options table.
 */
class LogEnabled extends BoolOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'log_enabled';
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): ?string
    {
        return 'yes';
    }
}
