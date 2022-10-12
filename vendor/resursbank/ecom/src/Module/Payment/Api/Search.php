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
use Resursbank\Ecom\Lib\Collection\Collection;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\PaymentCollection;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

use function is_array;

/**
 * POST /payments/find_payment for looking up payments in MAPI. Can be used to find legacy payments.
 */
class Search
{
    /**
     * @param Mapi $mapi
     */
    public function __construct(
        private readonly Mapi $mapi = new Mapi()
    ) {
    }

    /**
     * @param string $storeId
     * @param string $orderReference
     * @param string $governmentId
     * @return Collection
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public function call(
        string $storeId,
        ?string $orderReference = null,
        ?string $governmentId = null
    ): Collection {
        $payload = [];
        if ($governmentId && trim($governmentId) !== '') {
            $payload['governmentId'] = $governmentId;
        }
        if ($orderReference && trim($orderReference) !== '') {
            $payload['orderReference'] = $orderReference;
        }
        $payload['storeId'] = $storeId;

        $curl = new Curl(
            url: $this->mapi->getUrl(
                route: sprintf('%s/payments/search', Mapi::PAYMENT_ROUTE)
            ),
            requestMethod: RequestMethod::POST,
            payload: $payload,
            contentType: ContentType::JSON,
            authType: AuthType::JWT,
            responseContentType: ContentType::JSON
        );

        $data = $curl->exec()->body;

        $content = (
            $data instanceof stdClass &&
            isset($data->content) &&
            is_array(value: $data->content)
        ) ? $data->content : [];

        $result = DataConverter::arrayToCollection(
            data: $content,
            targetType: Payment::class
        );

        if (!$result instanceof PaymentCollection) {
            throw new InvalidTypeException(message: 'Expected PaymentCollection.');
        }

        return $result;
    }
}
