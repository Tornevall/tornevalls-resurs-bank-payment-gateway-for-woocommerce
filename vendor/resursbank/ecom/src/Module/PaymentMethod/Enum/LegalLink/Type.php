<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Enum\LegalLink;

/**
 * @codingStandardsIgnoreStart
 */
enum Type: string
{
    case GENERAL_TERMS = 'GENERAL_TERMS';
    case SEKKI = 'SEKKI';
    case PRICE_INFO = 'PRICE_INFO';
}
