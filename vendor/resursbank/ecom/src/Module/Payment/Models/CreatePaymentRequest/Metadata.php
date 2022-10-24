<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Metadata information class for payments. Currently, it does not have a proper collection.
 */
class Metadata extends Model
{
    /**
     * @param string|null $creator
     * @param array|null $custom
     * @param StringValidation $stringValidation
     * @param ArrayValidation $arrayValidation
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly ?string $creator = null,
        public readonly ?array $custom = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly ArrayValidation $arrayValidation = new ArrayValidation(),
    ) {
        //$this->validateCreator();
        //$this->validateCustom();
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateCreator(): void
    {
        if ($this->creator !== null) {
            $this->stringValidation->length(value: $this->creator, min: 0, max: 50);
        }
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateCustom(): void
    {
        if ($this->custom !== null) {
            $this->arrayValidation->isAssoc(data: $this->custom);
        }
    }
}
