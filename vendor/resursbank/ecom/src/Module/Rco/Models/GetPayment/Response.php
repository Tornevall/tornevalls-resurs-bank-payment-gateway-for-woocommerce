<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Models\GetPayment;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Module\Rco\Models\MetaDataCollection;

/**
 * Defines a response from the GetPayment API call.
 */
class Response extends Model
{
    /**
     * @param string $id
     * @param float $totalAmount
     * @param float $limit
     * @param Customer $customer
     * @param Address $deliveryAddress
     * @param string $booked
     * @param string $paymentMethodId
     * @param string $paymentMethodName
     * @param bool $fraud
     * @param bool $frozen
     * @param array $status
     * @param string $storeId
     * @param string $paymentMethodType
     * @param int $totalBonusPoints
     * @param ?string $finalized
     * @param ?MetaDataCollection $metadata
     * @param ?PaymentDiffCollection $paymentDiffs
     */
    public function __construct(
        public string $id,
        public float $totalAmount,
        public float $limit,
        public Customer $customer,
        public Address $deliveryAddress,
        public string $booked,
        public string $paymentMethodId,
        public string $paymentMethodName,
        public bool $fraud,
        public bool $frozen,
        public array $status,
        public string $storeId,
        public string $paymentMethodType,
        public int $totalBonusPoints,
        public ?string $finalized = null,
        public ?MetaDataCollection $metadata = null,
        public ?PaymentDiffCollection $paymentDiffs = null,
    ) {
    }
}
