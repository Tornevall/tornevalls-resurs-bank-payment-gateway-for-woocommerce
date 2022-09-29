<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Api;

use JsonException;
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
use Resursbank\Ecom\Module\RcoCallback\Repository;
use Resursbank\Ecom\Module\RcoCallback\Models\RegisterCallback\Request;

/**
 * Handles callback registration.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RegisterCallback
{
    /**
     * @param string $eventName
     * @param Request $request
     * @return void
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     * @throws AuthException
     */
    public function call(string $eventName, Request $request): void
    {
        if (!isset(Config::$instance->basicAuth)) {
            throw new EmptyValueException(message: 'Basic auth credentials not set in Config');
        }

        $curl = new Curl(
            url: $this->getApiUrl(eventName: $eventName),
            requestMethod: RequestMethod::POST,
            payload: $request->toArray(),
            authType: AuthType::BASIC,
            responseContentType: ContentType::RAW
        );

        try {
            $curl->exec();
        } catch (CurlException $exception) {
            Config::$instance->logger->error(message: $exception);
            throw $exception;
        }
    }

    /**
     * Gets the API URL to use
     *
     * @param string $eventName
     * @return string
     */
    private function getApiUrl(string $eventName): string
    {
        return 'https://' . Repository::getApiHostname() . '/callbacks/' . $eventName;
    }
}
