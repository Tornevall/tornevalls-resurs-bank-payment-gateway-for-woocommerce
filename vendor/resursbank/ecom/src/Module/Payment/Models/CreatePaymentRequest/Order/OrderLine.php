<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Validation\FloatValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;

use function is_float;
use function is_string;

/**
 * Defines a product in an order.
 */
class OrderLine extends Model
{
    /**
     * @param string|null $description
     * @param string|null $reference
     * @param string|null $quantityUnit
     * @param float $quantity
     * @param float $vatRate
     * @param float|null $unitAmountIncludingVat
     * @param float $totalAmountIncludingVat
     * @param float|null $totalVatAmount
     * @param OrderLineType|null $type
     * @param StringValidation $stringValidation
     * @param FloatValidation $floatValidation
     * @throws IllegalValueException
     * @todo $quantity could be a float, or shift between float and int.
     *      We have no idea at the moment.
     */
    public function __construct(
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly ?string $quantityUnit,
        public readonly float $quantity,
        public readonly float $vatRate,
        public readonly ?float $unitAmountIncludingVat,
        public readonly float $totalAmountIncludingVat,
        public readonly ?float $totalVatAmount,
        public readonly ?OrderLineType $type,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly FloatValidation $floatValidation = new FloatValidation(),
    ) {
        $this->validateDescription();
        $this->validateReference();
        $this->validateQuantityUnit();
        $this->validateVatRate();
        $this->validateQuantity();
        $this->validateUnitAmountIncludingVat();
        $this->validateTotalAmountIncludingVat();
        $this->validateTotalVatAmount();
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateDescription(): void
    {
        if (is_string(value: $this->description)) {
            $this->stringValidation->length(
                value: $this->description,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateReference(): void
    {
        if (is_string(value: $this->reference)) {
            $this->stringValidation->length(
                value: $this->reference,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateQuantityUnit(): void
    {
        if (is_string(value: $this->quantityUnit)) {
            $this->stringValidation->length(
                value: $this->quantityUnit,
                min: 0,
                max: 50
            );
        }
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateVatRate(): void
    {
        $this->floatValidation->length(
            value: $this->vatRate,
            min: 0,
            max: 2
        );

        $this->floatValidation->inRange(
            value: $this->vatRate,
            min: 0,
            max: 99.99
        );
    }

    /**
     * @throws IllegalValueException
     */
    private function validateQuantity(): void
    {
        $this->floatValidation->length(
            value: $this->quantity,
            min: 0,
            max: 2
        );

        $this->floatValidation->inRange(
            value: $this->quantity,
            min: 0,
            max: 9999999999.99
        );
    }

    /**
     * @throws IllegalValueException
     */
    private function validateUnitAmountIncludingVat(): void
    {
        if (is_float(value: $this->unitAmountIncludingVat)) {
            $this->floatValidation->length(
                value: $this->unitAmountIncludingVat,
                min: 0,
                max: 2
            );

            $this->floatValidation->inRange(
                value: $this->unitAmountIncludingVat,
                min: 0,
                max: 9999999999.99
            );
        }
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateTotalAmountIncludingVat(): void
    {
        $this->floatValidation->length(
            value: $this->totalAmountIncludingVat,
            min: 0,
            max: 2
        );

        $this->floatValidation->inRange(
            value: $this->totalAmountIncludingVat,
            min: 0,
            max: 9999999999.99
        );
    }

    /**
     * @throws IllegalValueException
     * @returns void
     */
    private function validateTotalVatAmount(): void
    {
        if ($this->totalVatAmount !== null) {
            $this->floatValidation->length(
                value: $this->totalVatAmount,
                min: 0,
                max: 2
            );

            $this->floatValidation->inRange(
                value: $this->totalVatAmount,
                min: 0,
                max: 9999999999.99
            );
        }
    }
}
