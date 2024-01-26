<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\BoolOption;
use Resursbank\Woocommerce\Database\OptionInterface;
use Resursbank\Woocommerce\Database\Options\Api\StoreCountryCode;

/**
 * Implementation of resursbank_get_address_enabled value in options table.
 */
class EnableGetAddress extends BoolOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'get_address_enabled';
    }

    /**
     * Control getAddress function by country code.
     */
    public static function getData(): bool
    {
        return self::isGetAddressAllowedByGdprRule() &&
            self::isCountryCodeSe() &&
            parent::getData();
    }

    /**
     * Independent country code check (for advanced section).
     */
    public static function isCountryCodeSe(): bool
    {
        return StoreCountryCode::getCurrentStoreCountry() === 'SE';
    }

    /**
     * Quick fix for entirely disable getAddress widget when gdpr rules are applied.
     */
    public static function isGetAddressAllowedByGdprRule(): bool
    {
        return defined(constant_name: 'ALLOW_GET_ADDRESS') && ALLOW_GET_ADDRESS;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): ?string
    {
        return 'yes';
    }
}
