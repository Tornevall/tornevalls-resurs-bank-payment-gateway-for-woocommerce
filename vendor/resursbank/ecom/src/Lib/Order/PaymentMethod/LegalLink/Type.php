<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Order\PaymentMethod\LegalLink;

/**
 * @codingStandardsIgnoreStart
 */
enum Type: string
{
    case GENERAL_TERMS = 'GENERAL_TERMS';
    case SECCI = 'SECCI';
    case PRICE_INFO = 'PRICE_INFO';
}
