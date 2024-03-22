<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Locale\Language as EcomLanguage;
use Resursbank\Woocommerce\Database\Options\Api\StoreCountryCode;
use Throwable;

/**
 * Utility methods for language-related things.
 */
class Language
{
    public const DEFAULT_LANGUAGE = EcomLanguage::EN;

    /**
     * Attempts to somewhat safely fetch the correct site language.
     *
     * @return EcomLanguage Configured language or self::DEFAULT_LANGUAGE if no matching language found in Ecom
     */
    public static function getSiteLanguage(): EcomLanguage
    {
        // Default language
        $return = self::DEFAULT_LANGUAGE;

        try {
            $storeCountryCode = apply_filters(
                'resurs_store_country_code',
                StoreCountryCode::getCurrentStoreCountry()
            );

            // Set default language locale.
            $useLocale = self::getLanguageFromLocaleString(
                locale: get_locale()
            );

            // Use default locale if current store is not present or failing.
            if ($storeCountryCode !== '') {
                $useLocale = WooCommerce::getEcomLocale(
                    countryLocale: $storeCountryCode
                );
            }

            // Try to convert to an EcomLanguage object
            $return = EcomLanguage::tryFrom(
                value: strtolower(string: $useLocale)
            ) ?? self::DEFAULT_LANGUAGE;
        } catch (Throwable) {
            // If an error occurs, keep the default language
        }

        return $return;
    }

    /**
     * Maps Norwegian Bokmål ('nb') and Nynorsk ('nn') locale to 'no' as required by certain library.
     */
    private static function mapNbToNo(string $localeString): string
    {
        return $localeString === 'nb' || $localeString === 'nn'
            ? 'no'
            : $localeString;
    }

    /**
     * Extracts the language part from a locale definition.
     */
    private static function getLanguageFromLocaleString(string $locale): string
    {
        $languagePart = explode(separator: '_', string: $locale)[0];

        return self::mapNbToNo(localeString: $languagePart);
    }
}
