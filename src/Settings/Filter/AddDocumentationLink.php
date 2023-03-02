<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings\Filter;

use Resursbank\Woocommerce\Util\Translator;

use function is_array;

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
            hook_name: 'plugin_row_meta',
            callback: 'Resursbank\Woocommerce\Settings\Filter\AddDocumentationLink::exec',
            accepted_args: 2
        );
    }

    /**
     * Exec.
     */
    public static function exec(mixed $links, mixed $file): mixed
    {
        if (
            is_array(value: $links) &&
            $file === RESURSBANK_MODULE_DIR_NAME . '/init.php'
        ) {
            $links[] = '<a href="blank" target="_blank">' .
                Translator::translate(phraseId: 'documentation') .
                '</a>';
        }

        return $links;
    }
}
