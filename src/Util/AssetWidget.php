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
    case GetAddressJs;
    case GetAddressCss;
    case PaymentMethodJs;
}
