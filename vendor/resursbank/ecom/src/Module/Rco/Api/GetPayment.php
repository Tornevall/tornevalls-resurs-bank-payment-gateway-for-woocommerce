<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Rco\Api;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Ecom\Module\Rco\Repository;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Module\Rco\Models\GetPayment\Response;

/**
 * Handles fetching of RCO payment sessions.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetPayment
{
    /**
     * Make call to API.
     *
     * @param string $orderReference
     * @return Response
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws AuthException
     * @throws IllegalTypeException
     * @psalm-suppress MixedInferredReturnType
     */
    public function call(string $orderReference): Response
    {
        try {
            $curl = new Curl(
                url: $this->getApiUrl(orderReference: $orderReference),
                requestMethod: RequestMethod::GET,
                contentType: ContentType::URL,
                authType: AuthType::BASIC,
                responseContentType: ContentType::JSON
            );
            $response = $curl->exec();
        } catch (CurlException $exception) {
            Config::$instance->logger->error(message: $exception);
            throw $exception;
        }

        /** @psalm-suppress MixedReturnStatement */
        return DataConverter::stdClassToType(
            object: $response->body,
            type: Response::class
        );
    }

    /**
     * Gets the API URL to use
     *
     * @param string $orderReference
     * @return string
     */
    private function getApiUrl(string $orderReference): string
    {
        return 'https://' . Repository::getApiHostname() . '/checkout/payments/' . $orderReference;
    }
}
