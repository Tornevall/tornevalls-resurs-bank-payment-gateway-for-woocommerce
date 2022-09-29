<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Api;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Ecom\Module\RcoCallback\Models\Callback;
use Resursbank\Ecom\Module\RcoCallback\Models\CallbackCollection;
use Resursbank\Ecom\Module\RcoCallback\Repository;

/**
 * Handles fetching of a list of configured callbacks
 */
class GetCallbacks
{
    /**
     * @return CallbackCollection
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ValidationException
     * @throws ReflectionException
     * @throws AuthException
     * @throws IllegalTypeException
     */
    public function call(): CallbackCollection
    {
        if (!isset(Config::$instance->basicAuth)) {
            throw new EmptyValueException(message: 'Basic auth credentials not set in Config');
        }

        $curl = new Curl(
            url: $this->getApiUrl(),
            requestMethod: RequestMethod::GET,
            contentType: ContentType::EMPTY,
            authType: AuthType::BASIC,
            responseContentType: ContentType::JSON
        );

        try {
            $response = $curl->exec();
            return DataConverter::arrayToCollection(
                data: $response->body,
                targetType: Callback::class
            );
        } catch (CurlException $exception) {
            Config::$instance->logger->error(message: $exception);
            throw $exception;
        }
    }

    /**
     * Gets the API URL to use
     *
     * @return string
     */
    private function getApiUrl(): string
    {
        return 'https://' . Repository::getApiHostname() . '/callbacks';
    }
}
