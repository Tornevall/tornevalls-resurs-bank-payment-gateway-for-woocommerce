<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Settings\Filter;

use Resursbankabpayments\Woocommerce\Util\Translator;

use function is_array;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add documentation link to Plugins page.
 */
class AddDocumentationLink
{
    /**
     * Add event listener to render the custom select element.
     */
    public static function register(): void
    {
        add_filter(
            'plugin_row_meta',
            'Resursbankabpayments\Woocommerce\Settings\Filter\AddDocumentationLink::exec',
            10,
            2
        );
    }

    /**
     * Exec.
     */
    public static function exec(mixed $links, mixed $file): mixed
    {
        if (
            is_array(value: $links) &&
            $file === RESURSBANKABPAYMENTS_MODULE_DIR_NAME . '/init.php'
        ) {
            $links[] = wp_kses(
                '<a href="blank" target="_blank">' .
                    Translator::translate(phraseId: 'documentation') .
                '</a>',
                ['a' => ['target' => true]]
            );
        }

        return $links;
    }
}
