<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Enum;

/**
 * Enum for payment statuses.
 */
enum Status: string
{
    case TASK_REDIRECTION_REQUIRED = 'TASK_REDIRECTION_REQUIRED';
    case INSPECTION = 'INSPECTION';
    case SUPPLEMENTING_REQUIRED = 'SUPPLEMENTING_REQUIRED';
    case FROZEN = 'FROZEN';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
