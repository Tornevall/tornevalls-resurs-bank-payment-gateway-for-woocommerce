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
use stdClass;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Ecom\Module\Rco\Models\UpdatePaymentReference\Request;
use Resursbank\Ecom\Module\Rco\Models\UpdatePaymentReference\Response;
use Resursbank\Ecom\Module\Rco\Repository;

/**
 * Handles updates of the RCO payment reference
 *
 * @SuppressWarnings (PHPMD.CouplingBetweenObjects)
 */
class UpdatePaymentReference
{
    /**
     * Makes call to the API
     *
     * @param Request $request
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
    public function call(Request $request, string $orderReference): Response
    {
        try {
            $response = Curl::put(
                url:  $this->getApiUrl(orderReference: $orderReference),
                payload: $request->toArray(),
                authType: AuthType::BASIC,
                responseContentType: ContentType::RAW
            );
        } catch (CurlException $exception) {
            Config::$instance->logger->error(message: $exception);
            throw $exception;
        }

        $responseObj = new stdClass();
        $responseObj->message = $response->body->message;
        $responseObj->code = $response->code;

        /** @psalm-suppress MixedReturnStatement */
        return DataConverter::stdClassToType(
            object: $responseObj,
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
        return 'https://' . Repository::getApiHostname() . '/checkout/payments/'
            . $orderReference . '/updatePaymentReference';
    }
}
