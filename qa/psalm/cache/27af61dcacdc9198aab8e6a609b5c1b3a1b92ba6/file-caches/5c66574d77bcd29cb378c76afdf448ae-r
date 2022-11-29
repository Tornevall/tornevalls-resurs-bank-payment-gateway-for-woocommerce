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
use Resursbank\Ecom\Lib\Collection\Collection;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Repository\Traits\DataResolver;
use Resursbank\Ecom\Lib\Repository\Traits\ModelConverter;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;

/**
 * Generic functionality to perform a GET call against the Merchant API and
 * convert the response to model instance(s).
 */
class Get
{
    use ExceptionLog;
    use ModelConverter;
    use DataResolver;

    /**
     * @param class-string $model | Convert cached data to model instance(s).
     * @param string $route
     * @param array $params
     * @param string $extractProperty
     * @param Mapi $mapi
     * @throws IllegalTypeException
     */
    public function __construct(
        private readonly string $model,
        private readonly string $route,
        private readonly array $params = [],
        private readonly string $extractProperty = '',
        private readonly Mapi $mapi = new Mapi(),
    ) {
        $this->validateModel(model: $model);
    }

    /**
     * @return Collection|Model
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
    public function call(): Collection|Model
    {
        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: $this->route
            ),
            requestMethod: RequestMethod::GET,
            payload: $this->params,
            contentType: ContentType::URL,
            authType: AuthType::JWT,
            responseContentType: ContentType::JSON
        );

        $data = $curl->exec()->body;

        return $this->convertToModel(
            data: $this->resolveResponseData(
                data: $data,
                extractProperty: $this->extractProperty
            ),
            model: $this->model
        );
    }
}
