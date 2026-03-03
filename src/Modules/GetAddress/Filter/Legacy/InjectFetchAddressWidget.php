<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter\Legacy;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Module\Widget\GetAddress\Html as Widget;
use Resursbank\Woocommerce\Util\HtmlSanitizer;
use Resursbank\Woocommerce\Util\Route;
use Throwable;

/**
 * Render get address form above the form on the checkout page.
 */
class InjectFetchAddressWidget
{
    /**
     * Renders and returns the content of the widget that fetches the customer
     * address.
     */
    public static function exec(): void
    {
        $errorMessage = '';

        try {
            Log::debug(
                message: 'Initialize getAddress with URL: ' . Route::getUrl(
                    route: Route::ROUTE_GET_ADDRESS
                )
            );

            /**
             * Create compatibility with template paragraphing when wpautop is executed.
             * This script works properly when themes keeps the script code separate
             * from other html-data. When this is not happening, our scripts
             * will be treated as html, and the <p>-tags are wrongfully added to the code.
             * When we merge the content by cleaning up the section that $paragraphs is using
             * this is avoided.
             *
             * See wp-includes/formatting.php for where $paragraphs are split up.
             */
            $result = preg_replace(
                pattern: '/\n\s*\n/m',
                replacement: ' ',
                subject: (new Widget())->content
            );

            // Escape output directly using wp_kses to satisfy WordPress.org security requirements.
            // We use wp_kses with an explicit allowlist to preserve form elements and
            // interactive components while still protecting against XSS.
            echo wp_kses(
                $result,
                HtmlSanitizer::getGetAddressFormAllowlist()
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via wp_kses
            );
        } catch (Throwable $e) {
            try {
                Config::getLogger()->error(
                    message: new GetAddressException(
                        message: 'Failed to render get address widget.',
                        previous: $e
                    )
                );
            } catch (ConfigException) {
                $errorMessage = 'ResursBank: failed to render get address widget.';
            }
        }

        if ($errorMessage === '') {
            return;
        }

        echo esc_html($errorMessage);
    }
}
