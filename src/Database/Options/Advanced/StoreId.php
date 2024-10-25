<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Settings\Api;

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

        return parent::getDefault();
    }
}
