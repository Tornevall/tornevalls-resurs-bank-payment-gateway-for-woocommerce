<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PriceSignage\Models;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines cost entity.
 */
class Cost extends Model
{
    /**
     * @param float $interest
     * @param int $months
     * @param float $totalCost
     * @param float $monthlyCost
     * @param float $agreementFee
     * @param float $effectiveInterest
     * @todo Ask about validation rules for this. Can the floats be negative? empty? min / max val? decimals always 2?
     * @todo Months is specified as int32, meaning I could get 2,147,483,647 back? Is that correct? ~179 million years.
     */
    public function __construct(
        public readonly float $interest,
        public readonly int $months,
        public readonly float $totalCost,
        public readonly float $monthlyCost,
        public readonly float $agreementFee,
        public readonly float $effectiveInterest,
    ) {
    }
}
