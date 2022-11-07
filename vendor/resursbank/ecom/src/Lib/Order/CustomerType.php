<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Order;

/**
 * Defines the types that a customer can be.
 */
enum CustomerType: string
{
    /**
     * Private person.
     */
    case NATURAL = 'NATURAL';

    /**
     * Company.
     */
    case LEGAL = 'LEGAL';
}
