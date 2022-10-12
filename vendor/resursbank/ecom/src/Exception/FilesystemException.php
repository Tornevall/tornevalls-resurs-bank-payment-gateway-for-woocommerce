<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Exception;

use Exception;

/**
 * Specifies a problem relating to the filesystem or a filesystem operation.
 */
class FilesystemException extends Exception
{
    public const CODE_FILE_MISSING = 1;
    public const CODE_FILE_EMPTY = 2;
}
