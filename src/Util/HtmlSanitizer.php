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

    /**
     * Allowlist for part payment widget HTML (from EcomPartPayment SDK).
     *
     * Includes:
     * - Basic structure (div, span, p, table, tr, td, th, a, br, strong, em)
     * - Form elements (form, input, select, option, button, label)
     * - Interactive elements with data attributes
     * - Inline styles for widget layout
     */
    public static function getPartPaymentWidgetAllowlist(): array
    {
        return [
            'div' => [
                'id' => true,
                'class' => true,
                'data-*' => true,
                'style' => true,
            ],
            'span' => [
                'id' => true,
                'class' => true,
                'data-*' => true,
                'style' => true,
            ],
            'p' => [
                'class' => true,
                'style' => true,
            ],
            'table' => [
                'class' => true,
                'style' => true,
            ],
            'thead' => [],
            'tbody' => [],
            'tr' => [
                'class' => true,
            ],
            'th' => [
                'class' => true,
            ],
            'td' => [
                'class' => true,
            ],
            'a' => [
                'href' => true,
                'class' => true,
                'id' => true,
                'target' => true,
                'rel' => true,
                'data-*' => true,
            ],
            'br' => [],
            'strong' => [],
            'em' => [],
            'b' => [],
            'i' => [],
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
            'button' => [
                'id' => true,
                'class' => true,
                'type' => true,
                'disabled' => true,
                'data-*' => true,
            ],
            'label' => [
                'for' => true,
                'class' => true,
            ],
            'ul' => [
                'class' => true,
            ],
            'ol' => [
                'class' => true,
            ],
            'li' => [
                'class' => true,
            ],
        ];
    }

    /**
     * Allowlist for WooCommerce Blocks checkout widgets (ReadMore, logos, payment info).
     *
     * Includes SDK-generated HTML with:
     * - Basic structure (b, br, strong, p, div, span, aside, ul, ol, li, table)
     * - ReadMore iframe (src, loading, title, allow, referrerpolicy, sandbox, dimensions)
     * - SVG logos/icons (svg, defs, g, path, polygon, circle with full styling)
     * - Data attributes for JavaScript integration
     * - Inline styles for layout and visibility
     *
     * @return array<string, array<string, bool>>
     */
    public static function getBlocksWidgetAllowlist(): array
    {
        return [
            'b' => [],
            'br' => [],
            'strong' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'p' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'div' => [
                'class' => true,
                'id' => true,
                'data-*' => true,
                'style' => true,
                'role' => true,
                'aria-*' => true,
            ],
            'span' => [
                'class' => true,
                'id' => true,
                'data-*' => true,
                'style' => true,
                'role' => true,
                'aria-*' => true,
            ],
            'aside' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'ul' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'ol' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'li' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'table' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'thead' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'tbody' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'tr' => [
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            'th' => [
                'class' => true,
                'id' => true,
                'style' => true,
                'scope' => true,
            ],
            'td' => [
                'class' => true,
                'id' => true,
                'style' => true,
                'colspan' => true,
                'rowspan' => true,
            ],
            'a' => [
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
                'class' => true,
                'id' => true,
                'style' => true,
            ],
            // ReadMore modal uses an iframe.
            'iframe' => [
                'src' => true,
                'loading' => true,
                'title' => true,
                'allow' => true,
                'referrerpolicy' => true,
                'sandbox' => true,
                'class' => true,
                'id' => true,
                'style' => true,
                'width' => true,
                'height' => true,
            ],
            // SVG (logos, spinners, warning icons).
            'svg' => [
                'class' => true,
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'viewbox' => true,
                'version' => true,
                'id' => true,
                'xmlns' => true,
                'xmlns:svg' => true,
                'xmlns:xlink' => true,
                'xml:space' => true,
                'role' => true,
                'aria-label' => true,
                'focusable' => true,
                'x' => true,
                'y' => true,
            ],
            'defs' => [
                'id' => true,
            ],
            'g' => [
                'id' => true,
                'transform' => true,
            ],
            'path' => [
                'class' => true,
                'd' => true,
                'id' => true,
                'style' => true,
                'fill' => true,
                'fill-opacity' => true,
                'fill-rule' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-miterlimit' => true,
            ],
            'polygon' => [
                'class' => true,
                'points' => true,
                'style' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-miterlimit' => true,
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
     * Get safe CSS properties for Blocks widget sanitization.
     *
     * These properties are temporarily allowed via safe_style_css filter
     * when sanitizing SDK-generated widget HTML.
     *
     * @return string[]
     */
    public static function getBlocksWidgetSafeStyles(): array
    {
        return [
            'display',
            'visibility',
            'opacity',
            'pointer-events',
            'position',
            'top',
            'right',
            'bottom',
            'left',
            'z-index',
            'transform',
            'transition',
            'float',
            'clear',
            'width',
            'height',
            'max-width',
            'min-width',
            'max-height',
            'min-height',
            'margin',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'padding',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'color',
            'background',
            'background-color',
            'text-align',
            'text-decoration',
            'line-height',
            'font-size',
            'font-weight',
            'border',
            'border-color',
            'border-width',
            'border-style',
            'border-radius',
        ];
    }

    /**
     * Temporarily extends WordPress safe inline style properties while executing a callback.
     *
     * Used when SDK widgets rely on style properties not in WordPress' default safe list.
     *
     * @param string[] $properties CSS property names to allow
     * @param callable():string $callback Function that performs wp_kses sanitization
     * @return string Sanitized HTML
     */
    public static function withSafeStyleCss(array $properties, callable $callback): string
    {
        $filter = static function (array $styles) use ($properties): array {
            foreach ($properties as $prop) {
                $styles[] = $prop;
            }
            return array_values(array_unique($styles));
        };

        add_filter('safe_style_css', $filter, 9999);

        try {
            return (string)$callback();
        } finally {
            remove_filter('safe_style_css', $filter, 9999);
        }
    }

    /**
     * Defense-in-depth: only allow iframes pointing to known Resurs domains.
     *
     * @param string $src The iframe src attribute value
     * @return bool True if the iframe source is allowed, false otherwise
     */
    public static function isAllowedIframeSrc(string $src): bool
    {
        $src = trim($src);
        if ($src === '') {
            return false;
        }

        $parts = wp_parse_url($src);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            return false;
        }

        if ($host === '') {
            return false;
        }

        // Allow production + known subdomains.
        if ($host === 'resurs.com' || str_ends_with($host, '.resurs.com')) {
            return true;
        }

        // Allow integration/test environments used by the SDK.
        if (str_ends_with($host, '.integration.resurs.com')) {
            return true;
        }

        return false;
    }

    /**
     * Normalize SDK HTML to survive wp_kses without losing key visuals.
     *
     * - Removes <style> blocks (wp_kses will strip them anyway, and their content can become stray text nodes).
     * - Inlines the warning icon fill color when the SDK uses class="st0" + a removed <style> rule.
     *
     * @param string $html The HTML to normalize
     * @return string Normalized HTML
     */
    public static function normalizeWidgetHtml(string $html): string
    {
        // Remove style tags completely.
        $html = (string)preg_replace('~<style[^>]*>.*?</style>~is', '', $html);

        // Inline fill color for elements that rely on a removed CSS class.
        // Note: This is intentionally simple because the SDK markup uses class="st0" without an existing fill attribute.
        $html = str_replace('class="st0"', 'class="st0" fill="#AA1E1E"', $html);
        $html = str_replace("class='st0'", "class='st0' fill='#AA1E1E'", $html);

        return $html;
    }
}
