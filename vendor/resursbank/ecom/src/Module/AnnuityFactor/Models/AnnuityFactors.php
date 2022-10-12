<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\AnnuityFactor\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * The response for /annuity_factors call.
 */
class AnnuityFactors extends Model
{
    /**
     * @param AnnuityInformationCollection $content
     */
    public function __construct(
        public readonly AnnuityInformationCollection $content,
    ) {
    }
}
