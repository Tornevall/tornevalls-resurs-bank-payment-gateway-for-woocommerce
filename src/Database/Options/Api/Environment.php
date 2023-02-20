<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Api;

use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\OptionInterface;
use ValueError;

/**
 * Implementation of resursbank_environment value in options table.
 */
class Environment extends Option implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'environment';
    }

    /**
     * @return string|null To be compliant with OptionInterface contact.
     */
    public static function getDefault(): ?string
    {
        return EnvironmentEnum::TEST->value;
    }

    /**
     * Get the data.
     *
     * @throws ValueError
     */
    public static function getData(): EnvironmentEnum
    {
        return EnvironmentEnum::from(
            value: parent::getRawData() ?? self::getDefault()
        );
    }
}
