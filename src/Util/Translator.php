<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Lib\Locale\Translator as EcomTranslator;
use Throwable;

/**
 * Translator wrapper.
 */
class Translator
{
    /**
     * Translate phrase id or kick it back. Log potential Throwable and display
     * message where possible (like in the admin panel).
     */
    public static function translate(
        string $phraseId,
        ?string $translationFile = null
    ): string {
        $result = $phraseId;

        try {
            $result = EcomTranslator::translate(
                phraseId: $phraseId,
                translationFile: $translationFile
            );
        } catch (Throwable $e) {
            Log::error(error: $e);
        }

        return Sanitize::sanitizeHtml(html: $result);
    }
}
