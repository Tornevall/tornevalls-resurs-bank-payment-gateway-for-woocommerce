<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Network;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Defines basic request header.
 */
class Header extends Model
{
    /**
     * @param string $key
     * @param string|int $value
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @todo Could be improved by fixing $value type (could just be string, int could be separate class if needed).
     */
    public function __construct(
        public readonly string $key,
        public readonly string|int $value,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateKey();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateKey(): void
    {
        $this->stringValidation->notEmpty(value: $this->key);
    }
}
