<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment\Api;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Mapi;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use stdClass;

/**
 * GET /payments/{orderReference}, similar to soap/RCO-REST getPayment,but for MAPI.
 */
class Get
{
    /**
     * @param Mapi $mapi
     */
    public function __construct(
        private readonly Mapi $mapi = new Mapi()
    ) {
    }

    /**
     * @param string $paymentId
     * @return Payment
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws JsonException
     */
    public function call(string $paymentId): Payment
    {
        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: sprintf('%s/payments/%s', Mapi::PAYMENT_ROUTE, $paymentId)
            ),
            requestMethod: RequestMethod::GET,
            authType: AuthType::JWT,
            responseContentType: ContentType::JSON
        );

        $data = $curl->exec()->body;

        $content = (
            $data instanceof stdClass
        ) ? $data : new stdClass();

        if (isset($content->metadata->custom) && $content->metadata->custom instanceof stdClass) {
            $content->metadata->custom = (array) $content->metadata->custom;
        }

        $result = DataConverter::stdClassToType(
            object: $content,
            type: Payment::class
        );

        if (!$result instanceof Payment) {
            throw new IllegalTypeException(message: 'Expected PaymentCollection.');
        }

        return $result;
    }
}
