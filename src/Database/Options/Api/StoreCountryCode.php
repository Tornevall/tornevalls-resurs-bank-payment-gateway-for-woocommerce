<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Api;

use Resursbank\Ecom\Config;
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

    /**
     * Returning a country code based on the store used in current config.
     */
    public static function getCurrentStoreCountry(): string
    {
        $currentStoreId = StoreId::getData();
        // Retrieve country code from cache for early response, updating only when we have new MAPI data.
        $return = (string)get_transient('resurs_merchant_country_code');

        // Return cached country code if store ID is missing or configuration is uninitialized.
        if ($currentStoreId === '' || !Config::hasInstance()) {
            return $return;
        }

        try {
            $storeList = Repository::getStores();

            /** @var Store $store */
            foreach ($storeList->getData() as $store) {
                if ($store->id === $currentStoreId) {
                    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                    set_transient(
                        'resurs_merchant_country_code',
                        $store->countryCode->value
                    );
                    $return = $store->countryCode->value;
                    break;
                }
            }
        } catch (Throwable) {
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return $return;
    }
}
