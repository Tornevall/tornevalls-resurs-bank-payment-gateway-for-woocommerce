<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Locale;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * English phrase that can be translated into any language.
 */
class Phrase extends Model
{
    /**
     * @param string $id
     * @param Translation $translation
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     */
    public function __construct(
        public string $id,
        public Translation $translation,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateId(value: $this->id);
    }

    /**
     * @param string $value
     * @return void
     * @throws EmptyValueException
     */
    public function validateId(string $value): void
    {
        $this->stringValidation->notEmpty(value: $value);
    }
}
