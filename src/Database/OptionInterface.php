<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database;

/**
 * Contact to communicate with database and extract options value.
 */
interface OptionInterface
{
    /**
     * Resolve option name (matching the same column in options table).
     */
    public static function getName(): string;

    /**
     * Default value to be utilised by Option class method to resolve data if
     * there is no matching row in the options table.
     */
    public static function getDefault(): ?string;
}
