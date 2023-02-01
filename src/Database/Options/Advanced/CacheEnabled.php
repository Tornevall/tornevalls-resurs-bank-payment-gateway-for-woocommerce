<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\BoolOption;

/**
 * Database interaction for cache_enabled option.
 */
class CacheEnabled extends BoolOption
{
    /**
     * @inheritdoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'cache_enabled';
    }

    /**
     * @inheritdoc
     */
    public static function getData(): string
    {
        $result = parent::getData();

        return $result !== '' ? $result : self::getDefault();
    }

    /**
     * @inheritdoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): string
    {
        return 'yes';
    }
}
