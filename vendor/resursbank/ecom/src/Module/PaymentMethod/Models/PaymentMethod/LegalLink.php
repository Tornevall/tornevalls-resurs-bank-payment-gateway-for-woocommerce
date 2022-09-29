<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Models\PaymentMethod;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Defines a legal info link.
 */
class LegalLink extends Model
{
    /**
     * @param string $url
     * @param string $type
     * @param bool $needToAppendPriceLast
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @todo $url validation could be improved to confirm string is a URL.
     * @todo $type validation to be replaced by Enum\LegalLink\Type when supported by DataConverter.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $type,
        public readonly bool $needToAppendPriceLast,
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
