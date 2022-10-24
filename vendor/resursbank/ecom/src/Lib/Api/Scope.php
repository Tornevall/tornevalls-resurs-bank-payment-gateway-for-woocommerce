<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Api;

/**
 * API scope types.
 *
 * @codingStandardsIgnoreStart
 */
enum Scope: string
{
    case MERCHANT_API = 'merchant-api';
    case MOCK_MERCHANT_API = 'mock-merchant-api';
}
