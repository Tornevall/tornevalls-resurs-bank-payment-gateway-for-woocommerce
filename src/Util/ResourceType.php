<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * Resource type enum.
 *
 * This enum defines the types of resources that can be used in the application,
 * specifically CSS and JS files.
 */
enum ResourceType: string
{
    case CSS = 'css';
    case JS = 'js';
}
