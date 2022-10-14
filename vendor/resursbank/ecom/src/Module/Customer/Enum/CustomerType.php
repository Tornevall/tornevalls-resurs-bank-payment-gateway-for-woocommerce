<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Customer\Enum;

/**
 * Available customer types in ecommerce.
 */
enum CustomerType: string
{
    case NATURAL = 'NATURAL';
    case LEGAL = 'LEGAL';
}
