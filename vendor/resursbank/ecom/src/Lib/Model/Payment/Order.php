<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLogCollection;
use Resursbank\Ecom\Lib\Model\Payment\Order\PossibleActionCollection;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Defines an order.
 */
class Order extends Model
{
    /**
     * @param string $orderReference
     * @param ActionLogCollection $actionLog
     * @param PossibleActionCollection $possibleActions
     * @param float $totalOrderAmount
     * @param float $canceledAmount
     * @param float $authorizedAmount
     * @param float $capturedAmount
     * @param float $refundedAmount
     * @param StringValidation $stringValidation
     * @throws IllegalCharsetException
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly string $orderReference,
        public readonly ActionLogCollection $actionLog,
        public readonly PossibleActionCollection $possibleActions,
        public readonly float $totalOrderAmount,
        public readonly float $canceledAmount,
        public readonly float $authorizedAmount,
        public readonly float $capturedAmount,
        public readonly float $refundedAmount,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateOrderReference();
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     */
    public function validateOrderReference(): void
    {
        $this->stringValidation->length(
            value: $this->orderReference,
            min: 1,
            max: 36
        );

        $this->stringValidation->matchRegex(
            value: $this->orderReference,
            pattern: '/[\w\-_]+/'
        );
    }
}
