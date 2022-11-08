<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Describes JWT token.
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 * @SuppressWarnings(PHPMD.CamelCaseParameterName)
 */
class Token extends Model
{
    /**
     * @var int
     */
    public readonly int $expires_in;

    /**
     * @param string $access_token
     * @param string $token_type
     * @param int $expires_in
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @todo $tokenType should be an enum. See ECP-227
     */
    public function __construct(
        public readonly string $access_token,
        public readonly string $token_type,
        int $expires_in,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateAccessToken();
        $this->validateTokenType();

        $this->expires_in = $expires_in + time();
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_in < time();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateAccessToken(): void
    {
        $this->stringValidation->notEmpty(value: $this->access_token);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateTokenType(): void
    {
        $this->stringValidation->notEmpty(value: $this->token_type);
    }
}
