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
        $return = (string)get_transient('resurs_merchant_country_code');

        try {
            // If site is missing a proper initial setup, StoreId request will throw an exception.
            // Also, if that request goes wrong it may do the same, so we will first verify that there are
            // credentials available.
            if (
                !Config::hasInstance() ||
                ClientId::getData() === '' &&
                ClientSecret::getData() === ''
            ) {
                return $return;
            }

            $currentStoreId = StoreId::getData();
        } catch (Throwable) {
            // All other errors should be suppressed.
            return $return;
        }

        // Failures from above should return pre-fetched data even if there is a chance that there is no data set.
        if ($currentStoreId === '') {
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
