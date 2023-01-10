<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\UniqueSellingPoint;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Module\PaymentMethod\Widget\ReadMore;
use ResursBank\Module\Data;
use Throwable;

/**
 * Checkout Unique selling Point (USP) functionality
 */
class Module
{
    /**
     * @throws IllegalTypeException
     * @throws ConfigException
     * @throws FilesystemException
     */
    public static function setCss(): void
    {
        if (!is_checkout()) {
            return;
        }

        try {
            $css = ReadMore::getCss();

            $filtered = apply_filters(
                hook_name: 'resursbank_readmore_css_display',
                value: '<style id="rb-rm-styles">' . $css . '</style>'
            );

            if (!is_string(value: $filtered)) {
                throw new IllegalTypeException(
                    message: 'Filtered CSS is no longer a string'
                );
            }

            echo Data::getEscapedHtml($filtered);
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
