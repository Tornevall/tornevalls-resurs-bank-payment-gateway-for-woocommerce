<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Api\Metadata;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Model\Payment\Metadata;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use stdClass;

/**
 * Updates Metadata on Payment objects
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Put
{
    /** @var Mapi  */
    private Mapi $mapi;

    public function __construct()
    {
        $this->mapi = new Mapi();
    }

    /**
     * @param string $paymentId
     * @param Metadata $metadata
     * @return Metadata
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    public function call(
        string $paymentId,
        Metadata $metadata
    ): Metadata {
        $payload = $metadata->toArray();
        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: Mapi::PAYMENT_ROUTE . '/' . $paymentId . '/metadata'
            ),
            requestMethod: RequestMethod::PUT,
            payload: $payload,
            authType: AuthType::JWT,
            responseContentType: ContentType::JSON,
            forceObject: empty($payload)
        );

        $data = $curl->exec()->body;

        $content = ($data instanceof stdClass) ? $data : new stdClass();

        $result = DataConverter::stdClassToType(
            object: $content,
            type: Metadata::class
        );

        if (!$result instanceof Metadata) {
            throw new IllegalTypeException(message: 'Expected Metadata');
        }

        return $result;
    }
}
