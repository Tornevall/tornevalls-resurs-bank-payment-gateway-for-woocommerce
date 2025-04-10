<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api\Controller\Admin;

use Resursbank\Ecom\Module\Store\Http\GetStoresController;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Settings\PartPayment;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Resolve JSON encoded list of current country code for store.
 */
class GetStoreCountry extends GetStoresController
{
    /**
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public function exec(): string
    {
        try {
            $response = [
                'storeCountry' => WooCommerce::getStoreCountry()
            ];
        } catch (Throwable) {
            $response = [
                'storeCountry' => 'N/A'
            ];
        }

        // Make sure we have saved data before using it within the search.
        if (StoreId::getData() !== '') {
            PartPayment::handleStoreIdUpdate(newStoreId: StoreId::getData());
            WooCommerce::validateAndUpdatePartPaymentMethod();
        }

        try {
            return json_encode(
                value: $response,
                flags: JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return '';
        }
    }
}
