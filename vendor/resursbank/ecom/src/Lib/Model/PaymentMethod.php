<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\PaymentMethod\LegalLinkCollection;
use Resursbank\Ecom\Lib\Validation\FloatValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;

/**
 * Defines payment method entity.
 *
 * NOTE: All Exceptions from namespace Validation extends ValidationException.
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class PaymentMethod extends Model
{
    /**
     * @param string $id
     * @param string $name
     * @param Type $type
     * @param float $minPurchaseLimit
     * @param float $maxPurchaseLimit
     * @param float $minApplicationLimit
     * @param float $maxApplicationLimit
     * @param LegalLinkCollection $legalLinks
     * @param bool $enabledForLegalCustomer
     * @param bool $enabledForNaturalCustomer
     * @param int $sortOrder
     * @param StringValidation $stringValidation
     * @param FloatValidation $floatValidation
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly Type $type,
        public readonly float $minPurchaseLimit,
        public readonly float $maxPurchaseLimit,
        public readonly float $minApplicationLimit,
        public readonly float $maxApplicationLimit,
        public readonly LegalLinkCollection $legalLinks,
        public readonly bool $enabledForLegalCustomer,
        public readonly bool $enabledForNaturalCustomer,
        public int $sortOrder = 0,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly FloatValidation $floatValidation = new FloatValidation()
    ) {
        $this->validateId();
        $this->validateName();
        $this->validateMinPurchaseLimit();
        $this->validateMaxPurchaseLimit();
        $this->validateMinApplicationLimit();
        $this->validateMaxApplicationLimit();
    }

    /**
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private function validateId(): void
    {
        $this->stringValidation->notEmpty(value: $this->id);
        $this->stringValidation->isUuid(value: $this->id);
    }

    /**
     * @throws EmptyValueException
     * @todo Add charset validation.
     */
    private function validateName(): void
    {
        $this->stringValidation->notEmpty(value: $this->name);
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateMinPurchaseLimit(): void
    {
        $this->floatValidation->isPositive(value: $this->minPurchaseLimit);
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateMaxPurchaseLimit(): void
    {
        $this->floatValidation->isPositive(value: $this->maxPurchaseLimit);
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateMinApplicationLimit(): void
    {
        $this->floatValidation->isPositive(value: $this->minApplicationLimit);
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateMaxApplicationLimit(): void
    {
        $this->floatValidation->isPositive(value: $this->maxApplicationLimit);
    }
}
