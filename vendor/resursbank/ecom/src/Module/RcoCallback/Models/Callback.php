<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines a callback object
 */
class Callback extends Model
{
    /**
     * @param string $eventType
     * @param string $uriTemplate
     */
    public function __construct(
        public string $eventType,
        public string $uriTemplate
    ) {
    }
}
