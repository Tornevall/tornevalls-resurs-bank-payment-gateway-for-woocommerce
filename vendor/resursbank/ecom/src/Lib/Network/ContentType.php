<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network;

/**
 * Applicable data types for CURL calls.
 *
 * @codingStandardsIgnoreStart
 */
enum ContentType
{
    case URL;
    case JSON;
    case RAW;
    case EMPTY;
}
