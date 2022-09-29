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
use Resursbank\Ecom\Lib\Repository\Traits\ModelConverter;
use stdClass;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;

use function is_array;
use function is_string;

/**
 * Generic functionality to perform a GET call against the Merchant API and
 * convert the response to model instance(s).
 */
class Get
{
    use ExceptionLog;
    use ModelConverter;

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
     */
    public function call(): Collection|Model
    {
        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: Mapi::COMMON_ROUTE . "/$this->route"
            ),
            requestMethod: RequestMethod::GET,
            payload: $this->params,
            contentType: ContentType::URL,
            authType: AuthType::JWT,
            responseContentType: ContentType::JSON
        );

        $data = $curl->exec()->body;

        if (!$data instanceof stdClass) {
            throw new ApiException(
                message: 'Invalid response from API. Not an stdClass.',
                code: 500,
            );
        }

        if ($this->extractProperty !== '') {
            if (
                !property_exists(
                    object_or_class: $data,
                    property:  $this->extractProperty
                )
            ) {
                throw new ApiException(
                    message: 'Invalid response from API. Missing property ' .
                        $this->extractProperty,
                    code: 500,
                );
            }

            /** @psalm-suppress MixedAssignment */
            $data = $data->{$this->extractProperty};
        }

        if (
            !$data instanceof stdClass &&
            !is_string(value: $data) &&
            !is_array(value: $data)
        ) {
            throw new ApiException(
                message: 'Invalid response from API. Not an stdClass or array.',
                code: 500,
            );
        }

        return $this->convertToModel(
            data: $data,
            model: $this->model
        );
    }
}
