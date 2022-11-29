<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Http;

use Error;
use Exception;
use JsonException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use stdClass;

use function file_get_contents;
use function get_class;

/**
 * Base controller class for JSON implementation. Execute arbitrary code,
 * construct a response as JSON encoded data.
 */
class Controller
{
    /**
     * Output JSON data.
     *
     * @param array $data
     * @return string
     */
    public function respond(
        array $data
    ): string {
        try {
            $result = json_encode(value: $data, flags: JSON_THROW_ON_ERROR);
        } catch (Exception) {
            $result = '{"error":"' . $this->translateError(phraseId: 'failed-to-encode') . '"}';
        }

        return $result;
    }

    /**
     * Shorthand method to log an Exception and create an error response.
     *
     * @param Exception $exception
     * @return string
     */
    public function respondWithError(Exception $exception): string
    {
        $this->log(exception: $exception);
        return $this->respond(
            data: ['error' => $this->getErrorMessage(exception: $exception)]
        );
    }

    /**
     * @param Exception $exception
     * @return int
     */
    public function getErrorResponseCode(Exception $exception): int
    {
        return match (get_class(object: $exception)) {
            HttpException::class => $exception->getCode(),
            CurlException::class => $exception->httpCode,
            default => 400
        };
    }

    /**
     * Mask messages from exceptions other than HttpException instances, to
     * ensure sensitive information is never rendered to the end client.
     *
     * @param Exception $exception
     * @return string
     */
    public function getErrorMessage(
        Exception $exception
    ): string {
        return $exception instanceof HttpException ?
            $exception->getMessage() :
            $this->translateError(phraseId: 'unknown-error');
    }

    /**
     * Resolve decoded input data.
     *
     * @param class-string $model
     * @return Model
     * @throws HttpException
     */
    public function getRequestModel(
        string $model
    ): Model {
        try {
            /** @var stdClass $result */
            $obj = json_decode(
                json: $this->getInputData(),
                associative: false,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );

            if (!$obj instanceof stdClass) {
                throw new JsonException(message: 'Malformed data.');
            }
        } catch (JsonException) {
            throw new HttpException(
                message: $this->translateError(phraseId: 'malformed-post-data'),
                code: 406
            );
        }

        try {
            return DataConverter::stdClassToType(
                object: $obj,
                type: $model
            );
        } catch (Exception | Error) {
            throw new HttpException(
                message: $this->translateError(phraseId: 'invalid-post-data'),
                code: 415
            );
        }
    }

    /**
     * @return string
     * @throws HttpException
     */
    public function getInputData(): string
    {
        $data = file_get_contents(filename: 'php://input');

        if (false === $data || $data === '') {
            throw new HttpException(
                message: $this->translateError(phraseId: 'missing-post-data'),
                code: 400
            );
        }

        return $data;
    }

    /**
     * @param Exception $exception
     * @return void
     */
    public function log(
        Exception $exception
    ): void {
        try {
            Config::getLogger()->debug(message: $exception);
        } catch (Exception) {
            // Logging is optional. Silence.
        }
    }

    /**
     * Translate error message without tossing Exception.
     *
     * @param string $phraseId
     * @return string
     */
    public function translateError(
        string $phraseId
    ): string {
        try {
            $result = Translator::translate(phraseId: $phraseId);
        } catch (Exception) {
            $result = 'Failed to translate error. Check debug log for info.';
        }

        return $result;
    }
}
