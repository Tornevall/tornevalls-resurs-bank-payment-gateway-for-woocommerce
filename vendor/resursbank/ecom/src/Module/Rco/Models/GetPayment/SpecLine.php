<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\GetPayment;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines an item in a payment. Items can be products, services (such
 * as shipping), etc.
 */
class SpecLine extends Model
{
    /**
     * @param string $id
     * @param string $artNo
     * @param string $description
     * @param float $quantity
     * @param string $unitMeasure
     * @param float $unitAmountWithoutVat
     * @param float $vatPct
     * @param float $totalVatAmount
     * @param float $totalAmount
     * @noinspection MessDetectorValidationInspection
     */
    public function __construct(
        public string $id,
        public string $artNo,
        public string $description,
        public float $quantity,
        public string $unitMeasure,
        public float $unitAmountWithoutVat,
        public float $vatPct,
        public float $totalVatAmount,
        public float $totalAmount
    ) {
    }
}
