<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Payment;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Collection\Collection;
use Resursbank\Ecom\Lib\Log\Traits\ExceptionLog;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection as ActionLogOrderLineCollection;
use Resursbank\Ecom\Module\Payment\Api\Cancel;
use Resursbank\Ecom\Module\Payment\Api\Capture;
use Resursbank\Ecom\Module\Payment\Api\Create;
use Resursbank\Ecom\Module\Payment\Api\Get;
use Resursbank\Ecom\Module\Payment\Api\Refund;
use Resursbank\Ecom\Module\Payment\Api\Search;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Application;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Customer;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Metadata;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order\OrderLineCollection;

/**
 * Payment repository.
 */
class Repository
{
    use ExceptionLog;

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
    public static function search(
        string $storeId,
        ?string $orderReference = null,
        ?string $governmentId = null
    ): Collection {
        return (new Search())->call(
            storeId: $storeId,
            orderReference: $orderReference,
            governmentId: $governmentId
        );
    }

    /**
     * @param string $paymentId
     *
     * @return Payment
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function get(
        string $paymentId
    ): Payment {
        $api = new Get();
        try {
            return $api->call(
                paymentId: $paymentId
            );
        } catch (Exception $e) {
            self::logException(exception: $e);
            throw $e;
        }
    }

    /**
     * Create payment
     *
     * @throws IllegalTypeException
     * @throws ValidationException
     * @throws AuthException
     * @throws EmptyValueException
     * @throws CurlException
     * @throws JsonException
     * @throws ApiException
     * @throws ReflectionException
     */
    public static function create(
        string $storeId,
        string $paymentMethodId,
        OrderLineCollection $orderLines,
        ?string $orderReference = null,
        ?Application $application = null,
        ?Customer $customer = null,
        ?Metadata $metadata = null,
        ?Options $options = null
    ): Payment {
        return (new Create())->call(
            storeId: $storeId,
            paymentMethodId: $paymentMethodId,
            orderLines: $orderLines,
            orderReference: $orderReference,
            application: $application,
            customer: $customer,
            metadata: $metadata,
            options: $options
        );
    }

    /**
     * Capture payment
     *
     * @param string $paymentId
     * @param ActionLogOrderLineCollection|null $orderLines
     * @param string|null $creator
     * @param string|null $transactionId
     * @param string|null $invoiceId
     * @return Payment
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function capture(
        string $paymentId,
        ?ActionLogOrderLineCollection $orderLines = null,
        ?string $creator = null,
        ?string $transactionId = null,
        ?string $invoiceId = null
    ): Payment {
        return (new Capture())->call(
            paymentId: $paymentId,
            orderLines: $orderLines,
            creator: $creator,
            transactionId: $transactionId,
            invoiceId: $invoiceId
        );
    }

    /**
     * Cancel payment
     *
     * @param string $paymentId
     * @param ActionLogOrderLineCollection|null $orderLines
     * @param string|null $creator
     * @return Payment
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function cancel(
        string $paymentId,
        ?ActionLogOrderLineCollection $orderLines = null,
        ?string $creator = null
    ): Payment {
        return (new Cancel())->call(
            paymentId: $paymentId,
            orderLines: $orderLines,
            creator: $creator
        );
    }

    /**
     * Refund payment
     *
     * @param string $paymentId
     * @param ActionLogOrderLineCollection|null $orderLines
     * @param string|null $creator
     * @param string|null $transactionId
     * @return \Resursbank\Ecom\Lib\Model\Payment
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function refund(
        string $paymentId,
        ?ActionLogOrderLineCollection $orderLines = null,
        ?string $creator = null,
        ?string $transactionId = null
    ): Payment {
        return (new Refund())->call(
            paymentId: $paymentId,
            orderLines: $orderLines,
            creator: $creator,
            transactionId: $transactionId
        );
    }
}
