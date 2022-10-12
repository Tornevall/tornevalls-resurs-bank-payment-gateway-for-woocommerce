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
 * Translated phrase. The phrase has to be translated to the languages listed
 * in the constructor, and cannot be an empty string. Base language is english.
 */
class Translation extends Model
{
    /**
     * @param string $sv
     * @param string $en
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     */
    public function __construct(
        public readonly string $sv,
        public readonly string $en,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateTranslation(value: $this->sv);
        $this->validateTranslation(value: $this->en);
    }

    /**
     * @param string $value
     * @return void
     * @throws EmptyValueException
     */
    public function validateTranslation(string $value): void
    {
        $this->stringValidation->notEmpty(value: $value);
    }
}
