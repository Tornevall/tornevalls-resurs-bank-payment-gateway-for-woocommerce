<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network;

/**
 * Applicable request methods.
 *
 * @codingStandardsIgnoreStart
 */
enum RequestMethod
{
    case GET;
    case POST;
    case PUT;
    case DELETE;
}

