<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\UniqueSellingPoint;

use Resursbank\Ecom\Module\Widget\ReadMore\Css as ReadMore;
use Resursbankabpayments\Woocommerce\Util\Log;
use Throwable;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

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
            'Resursbankabpayments\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint::setCss'
        );
    }

    /**
     * Render CSS.
     */
    public static function setCss(): void
    {
        if (!is_checkout()) {
            return;
        }

        try {
            $css = (new ReadMore())->content;

            echo '<style id="resursbankabpaygw-rm-styles">' . esc_html($css) . '</style>';
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }
}
