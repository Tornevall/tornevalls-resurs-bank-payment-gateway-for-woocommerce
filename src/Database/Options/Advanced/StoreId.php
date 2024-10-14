<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
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
     * Resolve data.
     */
    public static function getData(): string
    {
        $result = parent::getData();

        return $result !== '' ? $result : self::getDefault();
    }

    /**
     * Resolve single store as default or '' when multiple stores are available.
     */
    public static function getDefault(): string
    {
        // Don't bother to look up a store id if no credentials are set.
        // This may for some reason cause early memory exhaustion.
        if (ClientId::getData() === '' && ClientSecret::getData() === '') {
            return '';
        }

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
