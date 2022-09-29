<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PriceSignage\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines URI link entity.
 */
class UriLink extends Model
{
    /**
     * @param string $uri
     * @param string $language
     * @todo Can $language be empty? Is this an Enum value?
     * @todo Can $uri be empty? Is there a regex for this?
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $language,
    ) {
    }
}
