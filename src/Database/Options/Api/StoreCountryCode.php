<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Api;

use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Throwable;

/**
 * Implementation of resursbank_client_id value in options table.
 */
class StoreCountryCode extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'store_country_code';
    }

    public static function getCurrentStoreCountry(): string
    {
        $currentStoreId = StoreId::getData();

        if ($currentStoreId !== '') {
            try {
                $storeList = Repository::getStores();

                /** @var Store $store */
                foreach ($storeList->getData() as $store) {
                    if ($store->id === $currentStoreId) {
                        return $store->countryCode->value;
                    }
                }
            } catch (Throwable) {
            }
        }

        return '';
    }
}
