<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\Datatype\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

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
     * Resolve single store as default or '' when multiple stores are available.
     */
    public static function getDefault(): string
    {
        $result = parent::getDefault();

        try {
            $collection = Repository::getStores();

            if (count($collection) === 1) {
                $result = $collection[0]->id;
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        return $result;
    }
}
