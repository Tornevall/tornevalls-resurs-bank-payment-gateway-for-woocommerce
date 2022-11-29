<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Store\Models;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\IntValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\Store\Enum\Country;

/**
 * Defines a Store resource collected from the API.
 */
class Store extends Model
{
    /**
     * @param string $id | API identifier.
     * @param int $nationalStoreId
     * @param Country $countryCode
     * @param string $name
     * @param StringValidation $stringValidation
     * @param IntValidation $intValidation
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @todo $name will get a max length but that is not yet defined.
     */
    public function __construct(
        public readonly string $id,
        public readonly int $nationalStoreId,
        public readonly Country $countryCode,
        public readonly string $name,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly IntValidation $intValidation = new IntValidation()
    ) {
        $this->validateId();
        $this->validateNationalStoreId();
        $this->validateName();
    }

    /**
     * @throws EmptyValueException|IllegalValueException
     */
    private function validateId(): void
    {
        $this->stringValidation->notEmpty(value: $this->id);
        $this->stringValidation->isUuid(value: $this->id);
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateNationalStoreId(): void
    {
        $this->intValidation->isGt(value: $this->nationalStoreId, min: 0);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateName(): void
    {
        $this->stringValidation->notEmpty(value: $this->name);
    }
}
