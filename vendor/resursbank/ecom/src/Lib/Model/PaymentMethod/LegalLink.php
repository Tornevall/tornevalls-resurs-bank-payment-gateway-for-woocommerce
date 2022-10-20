<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Order\PaymentMethod\LegalLink\Type;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Defines a legal info link.
 */
class LegalLink extends Model
{
    /**
     * @param string $url
     * @param Type $type
     * @param bool $appendAmount
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @todo $url validation could be improved to confirm string is a URL.
     */
    public function __construct(
        public readonly string $url,
        public readonly Type $type,
        public readonly bool $appendAmount,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateUrl();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateUrl(): void
    {
        $this->stringValidation->notEmpty(value: $this->url);
    }
}
