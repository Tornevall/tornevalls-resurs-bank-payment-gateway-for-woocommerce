<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_store_id value in options table.
 */
class StoreId extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'store_id';
    }

    /**
     * Resolve data.
     */
    public static function getData(): string
    {
        $result = parent::getData();

        return $result !== '' ? $result : self::getDefault();
    }
}
