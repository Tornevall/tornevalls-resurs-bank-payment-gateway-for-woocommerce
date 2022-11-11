<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network\Curl;

use CurlHandle;
use JsonException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
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
use function is_object;

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
     * @throws ConfigException
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
     * @throws ConfigException
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
     * @throws ConfigException
     */
    private function getHttpCode(): int
    {
        $code = curl_getinfo(handle: $this->ch, option: CURLINFO_RESPONSE_CODE);

        if (is_string(value: $code) && is_numeric(value: $code)) {
            $code = (int) $code;
        }

        if (!is_int(value: $code)) {
            $exception = new IllegalTypeException(
                message: 'Curl http code is not an integer'
            );
            Config::getLogger()->error(message: $exception->getMessage());
            Config::getLogger()->error(message: $exception);
            throw $exception;
        }

        return $code;
    }

    /**
     * @param string $jsonError
     * @return void
     * @throws AuthException
     * @throws CurlException
     * @throws ConfigException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function throwCurlException(string $jsonError = ''): void
    {
        if (
            $this->httpCode === 401 ||
            ($this->httpCode === 400 && $jsonError === 'invalid_client')
        ) {
            $exception = new AuthException(
                message: 'Access denied. Please verify user credentials.'
            );
            Config::getLogger()->error(message: $exception->getMessage());
            Config::getLogger()->error(message: $exception);
            throw $exception;
        }

        $message = $this->getMessageFromErrorBody();
        $exception = new CurlException(
            message: !empty($message) ? $message : curl_error(handle: $this->ch),
            code: curl_errno(handle: $this->ch),
            body: $this->body,
            httpCode: $this->httpCode
        );
        Config::getLogger()->error(message: $exception->getMessage());
        Config::getLogger()->error(message: $exception);
        throw $exception;
    }

    /**
     * Attempts to parse an error message from $this->body if the HTTP response code is >= 400.
     *
     * @return string
     * @throws ConfigException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getMessageFromErrorBody(): string
    {
        if ($this->httpCode >= 400 && is_string(value: $this->body)) {
            try {
                $decoded = json_decode(
                    json: $this->body,
                    associative: false,
                    depth: 768,
                    flags: JSON_THROW_ON_ERROR
                );
                if (!is_object(value: $decoded)) {
                    throw new JsonException(message: 'json_decode did not produce an object');
                }
            } catch (JsonException $jsonException) {
                Config::getLogger()->error(message: $jsonException->getMessage());
                Config::getLogger()->error(message: $jsonException);
            }
        }

        $message = '';
        if (isset($decoded) && is_object(value: $decoded)) {
            if (isset($decoded->code) && (is_string(value: $decoded->code) || is_numeric(value: $decoded->code))) {
                $message .= $decoded->code;
            }
            if (isset($decoded->message) && is_string(value: $decoded->message)) {
                $message .= (!empty($message) ? ', ' : '') . $decoded->message . ' ';
            }
        }

        return $message;
    }

    /**
     * @return void
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ConfigException
     */
    private function validateBody(): void
    {
        if ($this->contentType === ContentType::JSON) {
            if (!is_string(value: $this->body)) {
                $exception = new IllegalTypeException(
                    message: 'Body is not a string, but should be for JSON content type.'
                );
                Config::getLogger()->error(message: $exception->getMessage());
                throw $exception;
            }

            $this->stringValidation->notEmpty(value: $this->body);

            $content = json_decode(
                json: $this->body,
                associative: false,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );

            if (!is_array(value: $content) && !$content instanceof stdClass) {
                $exception = new IllegalValueException(
                    message: 'Decoded JSON body is not an object.'
                );
                Config::getLogger()->error(message: $exception->getMessage());
                Config::getLogger()->error(message: $exception);
                throw $exception;
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
     * @throws ConfigException
     */
    private function validateHttpCode(): void
    {
        if ($this->httpCode >= 400 || $this->httpCode < 100) {
            $this->throwCurlException();
        }
    }
}
