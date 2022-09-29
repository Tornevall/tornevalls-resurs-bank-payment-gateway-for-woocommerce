<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network;

/**
 * API authentication types.
 *
 * @codingStandardsIgnoreStart
 */
enum AuthType
{
    case BASIC;
    case JWT;
    case NONE;
}
