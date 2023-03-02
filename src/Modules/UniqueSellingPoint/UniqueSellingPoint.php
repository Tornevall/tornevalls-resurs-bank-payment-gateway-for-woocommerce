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
use Throwable;

use function is_string;

/**
 * Checkout Unique selling Point (USP) functionality
 */
class UniqueSellingPoint
{
    /**
     * Init method for styling on checkout view.
     */
    public static function init(): void
    {
        add_action(
            'wp_head',
            'Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint::setCss'
        );
    }

    /**
     * @throws IllegalTypeException
     * @throws ConfigException
     * @throws FilesystemException
     * @todo
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

            echo $filtered;
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
