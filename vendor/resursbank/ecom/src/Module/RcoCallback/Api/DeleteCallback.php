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
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Module\RcoCallback\Repository;

/**
 * Handles deleting of callbacks
 */
class DeleteCallback
{
    /**
     * @param string $eventName
     * @return int
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     * @throws ReflectionException
     * @throws ApiException
     * @throws ConfigException
     * @throws IllegalValueException
     * @todo Check if ConfigException validation needs a test.
     * @todo Consider using LogException trait instead.
     * @todo I dropped an EmptyValueException, ensure tests are fine.
     */
    public function call(string $eventName): int
    {
        $curl = new Curl(
            url: $this->getApiUrl(eventName: $eventName),
            requestMethod: RequestMethod::DELETE,
            authType: AuthType::BASIC,
            responseContentType: ContentType::RAW
        );

        try {
            return $curl->exec()->code;
        } catch (CurlException $exception) {
            Config::getLogger()->error(message: $exception);
            throw $exception;
        }
    }

    /**
     * Gets the API URL to use
     *
     * @param string $eventName
     * @return string
     * @throws ConfigException
     */
    private function getApiUrl(string $eventName): string
    {
        return 'https://' . Repository::getApiHostname() . '/callbacks/' . $eventName;
    }
}
