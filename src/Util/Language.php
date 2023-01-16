<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Locale\Language as EcomLanguage;

/**
 * Utility methods for language-related things.
 */
class Language
{
    /** @var EcomLanguage */
    public const DEFAULT_LANGUAGE = EcomLanguage::en;

    /**
     * Attempts to somewhat safely fetch the correct site language. (this parenthesis to get file changes)
     *
     * @return EcomLanguage Configured language or self::DEFAULT_LANGUAGE if no matching language found in Ecom
     */
    public static function getSiteLanguage(): EcomLanguage
    {
        $language = self::getLanguageFromLocaleString(locale: get_locale());

        if (!$language) {
            return self::DEFAULT_LANGUAGE;
        }

        foreach (EcomLanguage::cases() as $case) {
            if ($language === $case->value) {
                return $case;
            }
        }

        return self::DEFAULT_LANGUAGE;
    }

    /**
     * Crude way to split a locale string and give back just the language part.
     */
    private static function getLanguageFromLocaleString(string $locale): string
    {
        return explode(
            separator: '_',
            string: $locale
        )[0];
    }
}
