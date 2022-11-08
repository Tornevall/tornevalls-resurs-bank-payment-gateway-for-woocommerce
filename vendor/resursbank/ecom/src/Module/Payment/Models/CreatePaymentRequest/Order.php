<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;

use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;

/**
 * Defines an order.
 */
class Order extends Model
{
    /**
     * @param OrderLineCollection $orderLines
     * @param string|null $orderReference
     * @param StringValidation $stringValidation
     * @param ArrayValidation $arrayValidation
     * @throws IllegalCharsetException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly OrderLineCollection $orderLines,
        public readonly ?string $orderReference = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly ArrayValidation $arrayValidation = new ArrayValidation(),
    ) {
        $this->validateOrderLines();
        $this->validateOrderReference();
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalTypeException
     */
    private function validateOrderLines(): void
    {
        $this->arrayValidation->isSequential(data: $this->orderLines->getData());
        $this->arrayValidation->length(
            data: $this->orderLines->getData(),
            min: 1,
            max: 1000
        );
        $this->arrayValidation->isOfType(
            data: $this->orderLines->getData(),
            type: OrderLine::class,
            compareFn: fn (mixed $value) => $value instanceof OrderLine
        );
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     */
    private function validateOrderReference(): void
    {
        if ($this->orderReference !== null) {
            $this->stringValidation->length(
                value: $this->orderReference,
                min: 1,
                max: 32
            );

            $this->stringValidation->matchRegex(
                value: $this->orderReference,
                pattern: '/[\w\-_]+/'
            );
        }
    }
}
