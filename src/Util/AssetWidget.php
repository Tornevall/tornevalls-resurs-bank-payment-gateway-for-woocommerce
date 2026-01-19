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
}
