<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\GetPayment;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines a payment.
 */
class PaymentSpec extends Model
{
    /**
     * @param SpecLineCollection $specLines
     * @param float $totalAmount
     * @param float $totalVatAmount
     * @param float $bonusPoints
     */
    public function __construct(
        public SpecLineCollection $specLines,
        public float $totalAmount,
        public float $totalVatAmount,
        public float $bonusPoints
    ) {
    }
}
