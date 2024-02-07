<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Locale\Language as EcomLanguage;
use Resursbank\Woocommerce\Database\Options\Api\StoreCountryCode;

/**
 * Utility methods for language-related things.
 */
class Language
{
    public const DEFAULT_LANGUAGE = EcomLanguage::en;

    /**
     * Attempts to somewhat safely fetch the correct site language.
     *
     * @return EcomLanguage Configured language or self::DEFAULT_LANGUAGE if no matching language found in Ecom
     */
    public static function getSiteLanguage(): EcomLanguage
    {
        $language = strtolower(
            string: StoreCountryCode::getCurrentStoreCountry() ?: self::getLanguageFromLocaleString(
                locale: get_locale()
            )
        );
        return EcomLanguage::tryFrom(
            value: $language
        ) ?? self::DEFAULT_LANGUAGE;
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
