<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * List of asset (js / css) widgets we can render.
 */
enum AssetWidget
{
    // Get address assets (frontend).
    case GetAddressJs;
    case GetAddressCss;

    // Payment method assets (frontend, blocks gateway).
    case PaymentMethodJs;

    // Dynamic CSS & JS content for admin panel.
    case AdminCss;
    case AdminJs;

    // Part payment assets (frontend).
    case PartPaymentJs;
    case PartPaymentCss;

    // Read more link assets (frontend).
    case ReadMoreJs;
    case ReadMoreCss;

    /**
     * Check whether the widget is a CSS widget.
     *
     * @return bool'
     */
    public function isCssWidget(): bool
    {
        return in_array(
            $this,
            [
                self::GetAddressCss,
                self::AdminCss,
                self::PartPaymentCss,
                self::ReadMoreCss
            ],
            true
        );
    }

    /**
     * Check whether the widget is a JS widget.
     *
     * @return bool
     */
    public function isJsWidget(): bool
    {
        return in_array(
            $this,
            [
                self::GetAddressJs,
                self::PaymentMethodJs,
                self::AdminJs,
                self::PartPaymentJs,
                self::ReadMoreJs
            ],
            true
        );
    }
}
