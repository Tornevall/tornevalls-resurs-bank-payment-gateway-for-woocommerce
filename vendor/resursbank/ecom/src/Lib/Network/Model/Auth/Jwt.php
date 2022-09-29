<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network\Model\Auth;

use Exception;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Network\Model\JwtToken;
use stdClass;

use function is_int;

/**
 * Defines JSON Token API authentication.
 */
class Jwt
{
    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $scope
     * @param string $grantType
     * @param JwtToken|null $token
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
        private JwtToken|null $token = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
        private readonly Mapi $mapi = new Mapi(),
    ) {
        $this->stringValidation->notEmpty(value: $this->clientId);
        $this->stringValidation->notEmpty(value: $this->clientSecret);
        $this->stringValidation->notEmpty(value: $this->scope);
        $this->stringValidation->notEmpty(value: $this->grantType);
    }

    /**
     * @param JwtToken|null $token
     * @return void
     */
    public function setToken(JwtToken|null $token): void
    {
        $this->token = $token;
    }

    /**
     * @return JwtToken
     * @throws AuthException
     * @throws IllegalTypeException
     */
    private function generateToken(): JwtToken
    {
        try {
            $tokenRequest = new Curl(
                url: $this->mapi->getUrl(
                    route: 'oauth2/token'
                ),
                requestMethod: RequestMethod::POST,
                payload: [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => $this->grantType,
                    'scope' => $this->scope,
                ],
                authType: AuthType::NONE
            );
        } catch (Exception $exception) {
            throw new AuthException(
                message: 'Unable to create Curl instance: ' . $exception->getMessage(),
                previous: $exception
            );
        }

        try {
            $response = $tokenRequest->exec();
        } catch (Exception $exception) {
            throw new AuthException(
                message: $exception->getMessage(),
                code: $exception->getCode()
            );
        }

        // @todo This requires MUCH better validation. We must check the type of each property, validate their values
        // @todo using charsets etc. (there are helper functions prepared in lib/Validation, fully tested).
        if (!$response->body instanceof stdClass) {
            throw new AuthException(
                message: 'Response body type is ' .
                gettype(value: $response->body) .
                'expected stdClass'
            );
        }

        if (
            !isset(
                $response->body->access_token,
                $response->body->token_type,
                $response->body->expires_in
            )
        ) {
            throw new AuthException(message: 'Failed to generate JWT token.');
        }

        if (
            !is_numeric(value: $response->body->expires_in) ||
            !is_int(value: $response->body->expires_in)
        ) {
            throw new IllegalTypeException(
                message: 'Received invalid expires_in value (' .
                    $response->body->expires_in .
                    '), was expecting integer'
            );
        }

        return new JwtToken(
            accessToken: $response->body->access_token,
            tokenType: $response->body->token_type,
            validUntil: time() + $response->body->expires_in,
        );
    }

    /**
     * Returns token, if we have no token or the current token is expired we
     * fetch a new one
     *
     * @return JwtToken
     * @throws AuthException
     * @throws IllegalTypeException
     */
    public function getToken(): JwtToken
    {
        if (!$this->token || $this->token->validUntil < time()) {
            $this->token = $this->generateToken();
        }

        return $this->token;
    }
}
