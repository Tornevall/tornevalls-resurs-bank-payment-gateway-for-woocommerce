<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Network\Auth;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Defines basic API authentication.
 */
class Basic extends Model
{
    /**
     * @param string $username
     * @param string $password
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @todo Add charset validation of username and password.
     */
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateUsername();
        $this->validatePassword();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validateUsername(): void
    {
        $this->stringValidation->notEmpty(value: $this->username);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validatePassword(): void
    {
        $this->stringValidation->notEmpty(value: $this->password);
    }
}
