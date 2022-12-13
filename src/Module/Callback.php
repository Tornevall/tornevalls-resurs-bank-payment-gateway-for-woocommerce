<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Callback\Authorization;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Callback\Management;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use ResursBank\Exception\CallbackException;
use Resursbank\Woocommerce\Util\Database;
use WC_Order;

/**
 * Callback handling by automation.
 */
class Callback
{
    /**
     * Callback automation goes here.
     * @param CallbackType $callbackType
     * @return bool
     * @throws ApiException
     * @throws AuthException
     * @throws CallbackException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws HttpException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function processCallback(CallbackType $callbackType): bool
    {
        $callbackModel = self::getCallbackModel(callbackType: $callbackType);
        $paymentId = self::getOrderReferenceFromCallbackModel(callbackModel: $callbackModel);

        if ($paymentId !== '') {
            Config::getLogger()->info(
                message: sprintf(
                    'Callback %s (status/action: %s, id: %s).',
                    $callbackType->value,
                    $callbackType === CallbackType::AUTHORIZATION ?
                        $callbackModel->status->value : $callbackModel->action->value,
                    $callbackType === CallbackType::MANAGEMENT ? $callbackModel->actionId : '-'
                )
            );
            Config::getLogger()->info(
                file_get_contents('php://input')
            );

            $order = Database::getOrderByReference(orderReference: $paymentId);

            return self::setWcOrderStatus(
                order: $order,
                paymentId: $paymentId
            );
        }

        throw new CallbackException(message: 'Could not handle callback.', code: 408);
    }

    /**
     * Get correct callback model for where we can find a paymentId.
     * @param CallbackType $callbackType
     * @return Authorization|Management
     * @throws HttpException
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    private static function getCallbackModel(CallbackType $callbackType): Authorization|Management
    {
        return match ($callbackType) {
            CallbackType::AUTHORIZATION => (new AuthorizationController())->getRequestModel(model: Authorization::class),
            CallbackType::MANAGEMENT => (new AuthorizationController())->getRequestModel(model: Management::class),
        };
    }

    /**
     * Get "paymentId" from respective callback model.
     * The callback model holds different kind of data, but the paymentId is always the same.
     * @param Authorization|Management $callbackModel
     * @return string
     */
    private static function getOrderReferenceFromCallbackModel(Authorization|Management $callbackModel): string
    {
        return $callbackModel->paymentId;
    }

    /**
     * @param WC_Order $order
     * @param string $paymentId
     * @return bool
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @noinspection PhpUnhandledExceptionInspection
     */
    private static function setWcOrderStatus(WC_Order $order, string $paymentId): bool
    {
        // Try-catch should not be placed here, as any exceptions will be caught at the json-response level.
        $resursPayment = PaymentRepository::get(paymentId: $paymentId);

        if (!$order->has_status(status: ['on-hold', 'processing', 'completed', 'cancelled'])) {
            $return = match ($resursPayment->status) {
                PaymentStatus::ACCEPTED => $order->payment_complete(),
                PaymentStatus::REJECTED => $order->update_status(
                    new_status: 'failed',
                    note: 'Payment rejected by Resurs.'
                ),
                default => $order->update_status(
                    new_status: 'on-hold',
                    note: 'Payment is waiting for more information from Resurs.'
                ),
            };
        }

        return $return ?? false;
    }
}