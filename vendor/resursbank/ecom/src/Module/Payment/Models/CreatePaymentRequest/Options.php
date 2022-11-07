<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\IntValidation;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\RedirectionUrls;

/**
 * Application data for a payment.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Options extends Model
{
    /**
     * @param bool|null $initiatedOnCustomerDevice
     * @param bool|null $handleManualInspection
     * @param bool|null $handleFrozenPayments
     * @param RedirectionUrls|null $redirectionUrls
     * @param Callbacks|null $callbacks
     * @param int|null $timeToLiveInMinutes
     * @param IntValidation $intValidation
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly ?bool $initiatedOnCustomerDevice,
        public readonly ?bool $handleManualInspection,
        public readonly ?bool $handleFrozenPayments,
        public readonly ?RedirectionUrls $redirectionUrls,
        public readonly ?Callbacks $callbacks,
        public readonly ?int $timeToLiveInMinutes,
        public readonly IntValidation $intValidation = new IntValidation()
    ) {
        $this->validateTimeToLiveInMinutes();
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateTimeToLiveInMinutes(): void
    {
        if ($this->timeToLiveInMinutes !== null) {
            $this->intValidation->inRange(value: $this->timeToLiveInMinutes, min: 1, max: 43200);
        }
    }
}
