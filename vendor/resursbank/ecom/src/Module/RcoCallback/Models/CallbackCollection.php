<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Models;

use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines a Callback collection
 */
class CallbackCollection extends Collection
{
    public function __construct(array $data)
    {
        parent::__construct($data, Callback::class);
    }
}
