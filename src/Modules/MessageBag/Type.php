<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

// Prevent direct access.
if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

/**
 * Possible message types and their corresponding CSS class for message div.
 */
enum Type: string
{
    case ERROR = 'error';
    case SUCCESS = 'notice-success';
}
