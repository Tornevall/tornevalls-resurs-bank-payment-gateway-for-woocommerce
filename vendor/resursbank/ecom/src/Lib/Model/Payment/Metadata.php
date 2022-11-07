<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\EntryCollection;

/**
 * Metadata information class for payments. Currently, it does not have a proper collection.
 */
class Metadata extends Model
{
    /**
     * @param string|null $creator
     * @param ?EntryCollection $custom
     */
    public function __construct(
        public readonly ?string $creator = null,
        public readonly ?EntryCollection $custom = null,
    ) {
    }
}
