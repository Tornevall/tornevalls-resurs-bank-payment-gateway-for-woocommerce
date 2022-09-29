<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module;

use Resursbank\Ecom\Config;

/**
 * Base implementation of a Module. Modules are intended as isolated libraries,
 * not allowed to communicate with each other directly to prevent code
 * complexity. Essentially, modifying the behaviour of one library should not
 * affect any other library.
 */
class Module
{
    /**
     * @param Config $config
     */
    public function __construct(
        protected readonly Config $config
    ) {
    }
}
