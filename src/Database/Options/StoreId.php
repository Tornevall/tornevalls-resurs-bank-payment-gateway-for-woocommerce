<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\StringOption;
use Throwable;

/**
 * Database interface for store_id in wp_options table.
 *
 * @todo Add validation through ECom if possible. See WOO-801.
 */
class StoreId extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'store_id';
    }

    /**
     * Fetches the first store id we can find if none is configured
     *
     * @throws ConfigException
     */
    public static function getDefault(): string
    {
        try {
            $stores = Repository::getStores(size: 1);

            if (isset($stores[0])) {
                return $stores[0]->id;
            }
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
        }

        return '';
    }
}
