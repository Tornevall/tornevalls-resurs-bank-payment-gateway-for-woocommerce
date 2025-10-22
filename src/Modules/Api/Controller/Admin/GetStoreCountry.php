<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api\Controller\Admin;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Store\Http\GetStoresController;
use Resursbank\Ecom\Module\UserSettings\Repository;
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
     * @todo This is used to automatically update the part payment method, and associated period, whne store changes. The reason is that, without configured ppw settings the checkout would at some point crash, so to fix it we added this to automatically add the settings (cause mercahnts who didn't use the PPW didn't enter that settings page and save settings so they were never in the database = crash). A better solution is, for example, to ensure that optimal vallues are always read directly by Ecom. We need to consider how to best implement this in Ecom though, but after we've done that we can basically remove this and all of its associated code which is quite a bit.
     * @throws ConfigException
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
        $store = Config::getStoreId();

        if ($store) {
            PartPayment::handleStoreIdUpdate(newStoreId: $store);
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
