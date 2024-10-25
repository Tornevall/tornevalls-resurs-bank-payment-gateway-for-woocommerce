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
use Resursbank\Woocommerce\Settings\Api;
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
        // Avoid fetching store ID if credentials are missing somewhere to prevent unnecessary API
        // calls and potential memory exhaustion, when no values are pre-saved in db.
        if (ClientId::getData() === '' || ClientSecret::getData() === '') {
            return '';
        }

        if (!Api::verifyAuthentication()) {
            // Proceed without storeId set if authentication fails.
            return '';
        }

        $result = parent::getDefault();

        try {
            // This should ensure we have at least one store id available on request, if the merchant
            // hasn't already configured this properly. If no data are stored already, the first available
            // store will be returned. Just beware, since this is an early init request, we have to make sure
            // that this is only fetched when there are proper and verified credentials set.
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
