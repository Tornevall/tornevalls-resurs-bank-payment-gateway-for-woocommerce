<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Api;

use Resursbank\Ecom\Lib\Model\Store\Store;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Util\WooCommerce;
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
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function getCurrentStoreCountry(): string
    {
        $return = (string)get_transient('resurs_merchant_country_code');

        if (!WooCommerce::isValidSetup()) {
            return $return;
        }

        $currentStoreId = self::getCurrentStoreId();

        if ($currentStoreId === '') {
            return $return;
        }

        return self::getAndSetStoreCountryCode(
            currentStoreId: $currentStoreId,
            defaultReturn: $return
        );
    }

    private static function getCurrentStoreId(): string
    {
        try {
            return StoreId::getData();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function getAndSetStoreCountryCode(string $currentStoreId, string $defaultReturn): string
    {
        try {
            $storeList = Repository::getStores();

            /** @var Store $store */
            foreach ($storeList->getData() as $store) {
                if ($store->id === $currentStoreId) {
                    set_transient(
                        'resurs_merchant_country_code',
                        $store->countryCode->value
                    );
                    return $store->countryCode->value;
                }
            }
        } catch (Throwable) {
            // Ignore errors.
        }

        return $defaultReturn;
    }
}
