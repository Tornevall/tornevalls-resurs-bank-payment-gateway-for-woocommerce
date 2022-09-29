<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\ArrayValidation;
use Resursbank\Ecom\Lib\Validation\StringValidation;

use function is_string;

/**
 * Metadata information class for payments. Currently, it does not have a proper collection.
 */
class Metadata extends Model
{
    /**
     * @param string $creator
     * @param array|null $custom
     * @param StringValidation $stringValidation
     * @param ArrayValidation $arrayValidation
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public function __construct(
        public readonly string $creator,
        public readonly ?array $custom = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly ArrayValidation $arrayValidation = new ArrayValidation(),
    ) {
        $this->validateCreator();
        $this->validateCustom();
    }

    /**
     * @return void
     * @throws IllegalValueException
     */
    private function validateCreator(): void
    {
        $this->stringValidation->length(
            value: $this->creator,
            min: 0,
            max: 50
        );
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalTypeException
     */
    private function validateCustom(): void
    {
        if ($this->custom !== null) {
            $this->arrayValidation->isAssoc(data: $this->custom);
            $this->arrayValidation->isOfType(
                data: $this->custom,
                type: 'string',
                compareFn: fn (mixed $value) => is_string($value)
            );
        }
    }
}
