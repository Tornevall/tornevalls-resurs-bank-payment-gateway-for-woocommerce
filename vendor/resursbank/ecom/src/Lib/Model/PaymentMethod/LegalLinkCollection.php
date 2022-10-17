<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Collection\Collection;

/**
 * Defines a LegalLink collection.
 */
class LegalLinkCollection extends Collection
{
    /**
     * @param array<int, LegalLink> $data
     * @throws IllegalTypeException
     */
    public function __construct(array $data)
    {
        parent::__construct(
            data: $data,
            type: LegalLink::class
        );
    }
}
