<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized wp_kses allowlists for HTML sanitization.
 *
 * This class provides reusable allowlist definitions for wp_kses() calls
 * throughout the plugin, ensuring consistent HTML sanitization and reducing code duplication.
 */
class HtmlSanitizer
{
    /**
     * Allowlist for payment info widget HTML (from PaymentInformation SDK).
     *
     * Includes:
     * - Basic formatting (b, br)
     * - Structure (div, table, thead, tbody, tr, th, td, span)
     * - SVG graphics (svg, defs, g, path, circle with inline styles and attributes)
     * - Inline CSS properties needed by SDK markup
     *
     * NOTE: The SDK generates HTML with inline styles (display: none, fill, stroke, etc).
     * These must be allowed via the safe_style_css filter to render correctly.
     */
    public static function getPaymentInfoAllowlist(): array
    {
        return [
            'b' => [],
            'br' => [],
            'div' => [
                'class' => true,
                'id' => true,
                'data-*' => true,
                'style' => true,
            ],
            'table' => [
                'class' => true,
            ],
            'thead' => [],
            'tbody' => [],
            'tr' => [
                'class' => true,
                'id' => true,
            ],
            'th' => [
                'class' => true,
            ],
            'td' => [
                'class' => true,
                'id' => true,
            ],
            'span' => [
                'class' => true,
                'id' => true,
            ],
            // SVG support (logo + spinner)
            'svg' => [
                'class' => true,
                'width' => true,
                'height' => true,
                // WordPress may normalize attribute names to lowercase.
                // Allow both to prevent accidental stripping.
                'viewBox' => true,
                'viewbox' => true,
                'version' => true,
                'id' => true,
                'xmlns' => true,
                'xmlns:svg' => true,
            ],
            'defs' => [
                'id' => true,
            ],
            'g' => [
                'id' => true,
                'transform' => true,
            ],
            'path' => [
                'style' => true,
                'd' => true,
                'id' => true,
                'fill' => true,
                'fill-opacity' => true,
                'fill-rule' => true,
                'stroke' => true,
            ],
            'circle' => [
                'class' => true,
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-miterlimit' => true,
            ],
        ];
    }

    /**
     * Allowlist for GetAddress form widget HTML.
     *
     * Includes:
     * - Form elements (form, input, label, button, select, option)
     * - Structure (div, span, p, br, a, script)
     * - Formatting (strong, em, headings, lists, tables)
     * - Data attributes for JavaScript integration
     * - Form validation attributes (required, disabled, readonly, checked)
     */
    public static function getGetAddressFormAllowlist(): array
    {
        return [
            'div' => [
                'id' => true,
                'class' => true,
                'data-*' => true,
                'style' => true,
                'data-block-name' => true,
            ],
            'form' => [
                'id' => true,
                'class' => true,
                'method' => true,
                'action' => true,
            ],
            'input' => [
                'id' => true,
                'class' => true,
                'type' => true,
                'name' => true,
                'value' => true,
                'placeholder' => true,
                'required' => true,
                'disabled' => true,
                'readonly' => true,
                'checked' => true,
                'data-*' => true,
            ],
            'label' => [
                'for' => true,
                'class' => true,
            ],
            'button' => [
                'id' => true,
                'class' => true,
                'type' => true,
                'disabled' => true,
                'data-*' => true,
            ],
            'select' => [
                'id' => true,
                'class' => true,
                'name' => true,
                'required' => true,
                'disabled' => true,
                'data-*' => true,
            ],
            'option' => [
                'value' => true,
                'selected' => true,
            ],
            'span' => [
                'id' => true,
                'class' => true,
                'data-*' => true,
            ],
            'p' => [
                'class' => true,
            ],
            'br' => [],
            'strong' => [],
            'em' => [],
            'a' => [
                'href' => true,
                'class' => true,
                'id' => true,
                'target' => true,
                'rel' => true,
            ],
            'script' => [
                'type' => true,
                'src' => true,
                'id' => true,
                'data-*' => true,
            ],
            'h1' => ['class' => true],
            'h2' => ['class' => true],
            'h3' => ['class' => true],
            'h4' => ['class' => true],
            'h5' => ['class' => true],
            'h6' => ['class' => true],
            'ul' => ['class' => true],
            'ol' => ['class' => true],
            'li' => ['class' => true],
            'table' => ['class' => true],
            'thead' => [],
            'tbody' => [],
            'tr' => ['class' => true],
            'th' => ['class' => true],
            'td' => ['class' => true],
        ];
    }

    /**
     * Allowlist for payment methods settings table HTML.
     *
     * Includes:
     * - Basic structure (style, div, table, thead, tbody, tr, th, td, p)
     * - Minimal styling support
     */
    public static function getPaymentMethodsTableAllowlist(): array
    {
        return [
            'style' => [],
            'div' => [
                'class' => true,
            ],
            'table' => [],
            'thead' => [],
            'tbody' => [],
            'tr' => [
                'id' => true,
            ],
            'th' => [],
            'td' => [],
            'p' => [
                'class' => true,
            ],
        ];
    }

    /**
     * Allowlist for support/about info widget HTML.
     *
     * Includes:
     * - Basic structure (div, table, tbody, tr, td, span, br)
     * - Minimal styling
     */
    public static function getSupportInfoAllowlist(): array
    {
        return [
            'div' => [
                'class' => true,
            ],
            'table' => [],
            'tbody' => [],
            'tr' => [],
            'td' => [
                'class' => true,
            ],
            'span' => [
                'class' => true,
            ],
            'br' => [],
        ];
    }

    /**
     * Allowlist for error message text (already escaped).
     *
     * Since error messages are already escaped via esc_html in getFailureReason(),
     * this allowlist contains no tags - only plain text is preserved.
     * This ensures the message is safe to output within the error div.
     */
    public static function getErrorMessageAllowlist(): array
    {
        return [];
    }
}
