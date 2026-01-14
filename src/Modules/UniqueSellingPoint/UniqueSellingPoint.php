<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\UniqueSellingPoint;

use Resursbank\Ecom\Module\Widget\ReadMore\Css as ReadMore;
use Resursbank\Ecom\Lib\Log\Logger;
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
            'wp_head',
            'Resursbank\Woocommerce\Modules\UniqueSellingPoint\UniqueSellingPoint::setCss'
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

            echo <<<EX
<style id="rb-rm-styles">
  $css
</style>
EX;
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }
    }
}
