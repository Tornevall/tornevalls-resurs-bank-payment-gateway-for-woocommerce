<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network\Curl;

use CurlHandle;
use JsonException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use stdClass;

use function is_array;
use function is_int;
use function is_string;

/**
 * Error handling of curl requests.
 */
class ErrorHandler
{
    /**
     * @var int
     */
    public readonly int $httpCode;

    /**
     * @param string|bool $body
     * @param CurlHandle $ch
     * @param ContentType $contentType
     * @param StringValidation $stringValidation
     * @throws IllegalTypeException
     */
    public function __construct(
        public readonly string|bool $body,
        public readonly CurlHandle $ch,
        public readonly ContentType $contentType,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->httpCode = $this->getHttpCode();
    }

    /**
     * @return void
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     */
    public function validate(): void
    {
        if ($this->body === false) {
            $this->throwCurlException();
        }

        $this->validateBody();
        $this->validateHttpCode();
    }

    /**
     * @return int
     * @throws IllegalTypeException
     */
    private function getHttpCode(): int
    {
        $code = curl_getinfo(handle: $this->ch, option: CURLINFO_RESPONSE_CODE);

        if (is_string(value: $code) && is_numeric(value: $code)) {
            $code = (int) $code;
        }

        if (!is_int(value: $code)) {
            throw new IllegalTypeException(
                message: 'Curl http code is not an integer'
            );
        }

        return $code;
    }

    /**
     * @param string $jsonError
     * @return void
     * @throws AuthException
     * @throws CurlException
     */
    private function throwCurlException(string $jsonError = ''): void
    {
        if (
            $this->httpCode === 401 ||
            ($this->httpCode === 400 && $jsonError === 'invalid_client')
        ) {
            throw new AuthException(
                message: 'Access denied. Please verify user credentials.'
            );
        }

        throw new CurlException(
            message: curl_error(handle: $this->ch),
            code: curl_errno(handle: $this->ch),
            body: $this->body,
            httpCode: $this->httpCode
        );
    }

    /**
     * @return void
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     */
    private function validateBody(): void
    {
        if ($this->contentType === ContentType::JSON) {
            if (!is_string(value: $this->body)) {
                throw new IllegalTypeException(
                    message: 'Body is not a string, but should be for JSON content type.'
                );
            }

            $this->stringValidation->notEmpty(value: $this->body);

            $content = json_decode(
                json: $this->body,
                associative: false,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );

            if (!is_array(value: $content) && !$content instanceof stdClass) {
                throw new IllegalValueException(
                    message: 'Decoded JSON body is not an object.'
                );
            }

            /** @psalm-suppress PossiblyInvalidPropertyFetch, MixedAssignment */
            $error = $content->error ?? '';

            if (is_string(value: $error) && $error !== '') {
                $this->throwCurlException(jsonError: $error);
            }
        }
    }

    /**
     * @return void
     * @throws AuthException
     * @throws CurlException
     */
    private function validateHttpCode(): void
    {
        if (curl_getinfo(handle: $this->ch, option: CURLINFO_HTTP_CODE) >= 400) {
            $this->throwCurlException();
        }
    }
}
