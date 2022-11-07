<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Repository\Api\Mapi;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt\Token;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Repository\Traits\DataResolver;
use Resursbank\Ecom\Lib\Repository\Traits\ModelConverter;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;

/**
 * Call to generate MAPI token and convert to Token model instance.
 *
 * @todo This class may prefer to be placed within a Module.
 */
class GenerateToken
{
    use ExceptionLog;
    use ModelConverter;
    use DataResolver;

    /**
     * @param Jwt $auth
     * @param Mapi $mapi
     */
    public function __construct(
        public readonly Jwt $auth,
        private readonly Mapi $mapi = new Mapi(),
    ) {
    }

    /**
     * @return Token
     * @throws ApiException
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws ConfigException
     */
    public function call(): Token
    {
        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: 'oauth2/token'
            ),
            requestMethod: RequestMethod::POST,
            payload: [
                'client_id' => $this->auth->clientId,
                'client_secret' => $this->auth->clientSecret,
                'grant_type' => $this->auth->grantType,
                'scope' => $this->auth->scope,
            ],
            authType: AuthType::NONE
        );

        $result = $this->convertToModel(
            data: $this->resolveResponseData(
                data: $curl->exec()->body
            ),
            model: Token::class
        );

        if (!$result instanceof Token) {
            throw new ApiException(
                message: 'Could not convert response to Token model.'
            );
        }

        return $result;
    }
}
