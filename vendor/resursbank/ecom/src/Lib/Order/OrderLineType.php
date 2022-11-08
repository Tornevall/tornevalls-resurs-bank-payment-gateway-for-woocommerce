<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Order;

/**
 * Defines the types that a product can have.
 */
enum OrderLineType: string
{
    case NORMAL = 'NORMAL';
    case PHYSICAL_GOODS = 'PHYSICAL_GOODS';
    case DIGITAL_GOODS = 'DIGITAL_GOODS';
    case DISCOUNT = 'DISCOUNT';
    case SHIPPING = 'SHIPPING';
    case FEE = 'FEE';
    case GIFT_CARD = 'GIFT_CARD';
    case OTHER_PAYMENT = 'OTHER_PAYMENT';
}
