<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

enum ResourceType: string
{
    case CSS = 'css';
    case JS = 'js';
}
