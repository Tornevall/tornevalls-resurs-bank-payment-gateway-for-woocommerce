<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Ecom\Lib\Log\LogLevel as EcomLogLevel;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\OptionInterface;
use ValueError;

/**
 * Implementation of resursbank_log_level value in options table.
 */
class LogLevel extends Option implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'log_level';
    }

    /**
     * Get configured LogLevel value.
     *
     * @throws ValueError
     */
    public static function getData(): EcomLogLevel
    {
        return EcomLogLevel::from(
            value: (int) (parent::getRawData() ?? self::getDefault())
        );
    }

    /**
     * @return string|null To be compliant with OptionInterface contact.
     */
    public static function getDefault(): ?string
    {
        return (string) EcomLogLevel::INFO->value;
    }
}
