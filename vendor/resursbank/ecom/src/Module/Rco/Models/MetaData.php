<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines a MetaData item.
 */
class MetaData extends Model
{
    /**
     * @param string $key
     * @param string $value
     */
    public function __construct(
        public string $key = '',
        public string $value = ''
    ) {
    }
}
