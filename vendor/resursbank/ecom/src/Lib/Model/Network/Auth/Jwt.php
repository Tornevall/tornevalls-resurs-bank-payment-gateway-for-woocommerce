<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Network\Auth;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Repository\Traits\DataResolver;
use Resursbank\Ecom\Lib\Repository\Traits\ModelConverter;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt\Token;

/**
 * Defines JSON Token API authentication.
 */
class Jwt extends Model
{
    use ModelConverter;
    use DataResolver;

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $scope
     * @param string $grantType
     * @param Token|null $token
     * @param StringValidation $stringValidation
     * @param Mapi $mapi
     * @throws EmptyValueException
     * @todo Add charset validation of id and secret.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $scope,
        public readonly string $grantType,
        private Token|null $token = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly Mapi $mapi = new Mapi(),
    ) {
        $this->validateClientId();
        $this->validateClientSecret();
        $this->validateScope();
        $this->validateGrantType();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validateClientId(): void
    {
        $this->stringValidation->notEmpty(value: $this->clientId);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validateClientSecret(): void
    {
        $this->stringValidation->notEmpty(value: $this->clientSecret);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validateScope(): void
    {
        $this->stringValidation->notEmpty(value: $this->scope);
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    public function validateGrantType(): void
    {
        $this->stringValidation->notEmpty(value: $this->grantType);
    }

    /**
     * @param Token|null $token
     * @return void
     */
    public function setToken(Token|null $token): void
    {
        $this->token = $token;
    }

    /**
     * @return Token|null
     */
    public function getToken(): Token|null
    {
        return $this->token;
    }
}
