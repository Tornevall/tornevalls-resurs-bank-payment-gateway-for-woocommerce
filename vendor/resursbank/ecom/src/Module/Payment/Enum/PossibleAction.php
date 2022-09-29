<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Enum;

/**
 * Possible order actions.
 */
enum PossibleAction: string
{
    case CAPTURE = 'CAPTURE';
    case PARTIAL_CAPTURE = 'PARTIAL_CAPTURE';
    case REFUND = 'REFUND';
    case PARTIAL_REFUND = 'PARTIAL_REFUND';
    case CANCEL = 'CANCEL';
    case PARTIAL_CANCEL = 'PARTIAL_CANCEL';
}
