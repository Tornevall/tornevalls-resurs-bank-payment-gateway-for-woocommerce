<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * Sanitizer methods.
 */
class Sanitize
{
    /**
     * Shorthand method to properly escape strings.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function sanitizeHtml(
        string $html,
        array $allowedTags = []
    ): string {
        return (string) wp_kses(
            (string) esc_html(text: $html),
            $allowedTags
        );
    }
}
