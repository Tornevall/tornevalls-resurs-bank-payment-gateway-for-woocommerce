<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\AnnuityFactor\Models;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Model\Payment\Order;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Application;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Customer;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Metadata;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;

/**
 * Describes an annuity factor.
 */
class AnnuityInformation extends Model
{
    /**
     * @param string $paymentPlanName
     * @param float $annuityFactor
     * @param int $durationInMonths
     * @param float $monthlyAdminFee
     * @param float $setupFee
     */
    public function __construct(
        public readonly string $paymentPlanName,
        public readonly float $annuityFactor,
        public readonly int $durationInMonths,
        public readonly float $monthlyAdminFee,
        public readonly float $setupFee
    ) {
    }
}
