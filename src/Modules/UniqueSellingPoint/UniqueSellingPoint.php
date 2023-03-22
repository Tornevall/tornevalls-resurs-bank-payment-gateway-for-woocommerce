<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\UniqueSellingPoint;

use Resursbank\Ecom\Module\PaymentMethod\Widget\ReadMore;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

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
            hook_name: 'wp_head',
            callback: 'Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint::setCss'
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
            $css = ReadMore::getCss();

            echo <<<EX
<style id="rb-rm-styles">
  $css
</style>
EX;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }
}
