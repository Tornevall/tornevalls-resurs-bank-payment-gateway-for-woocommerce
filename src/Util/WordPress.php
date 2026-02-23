<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

// Prevent direct access.
if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

/**
 * WordPress-specific utility helpers.
 */
class WordPress
{
    /**
     * Sanitize widget HTML while preserving required form markup.
     */
    public static function sanitizeWidgetHtml(string $html): string
    {
        return wp_kses(
            $html,
            [
                'div' => [
                    'id' => true,
                    'class' => true,
                    'style' => true,
                    'data-*' => true,
                    'aria-*' => true,
                    'role' => true,
                    'tabindex' => true
                ],
                'label' => [
                    'for' => true,
                    'class' => true,
                    'id' => true,
                    'data-*' => true,
                    'aria-*' => true
                ],
                'input' => [
                    'type' => true,
                    'id' => true,
                    'name' => true,
                    'value' => true,
                    'class' => true,
                    'checked' => true,
                    'data-*' => true,
                    'aria-*' => true
                ],
                'button' => [
                    'type' => true,
                    'id' => true,
                    'class' => true,
                    'data-*' => true,
                    'aria-*' => true,
                    'role' => true
                ],
                'span' => [
                    'class' => true,
                    'id' => true,
                    'data-*' => true,
                    'aria-*' => true
                ]
            ]
        );
    }
}
