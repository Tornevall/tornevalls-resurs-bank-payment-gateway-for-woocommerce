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
use ResursBank\Exception\CallbackException;
use Resursbank\Woocommerce\Util\Database;

/**
 * Callback handling by automation.
 */
class Callback
{
    /**
     * Callback automation goes here.
     * @param CallbackType $callbackType
     * @return void
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
    public static function processCallback(CallbackType $callbackType): void
    {
        $callbackModel = self::getCallbackModel(callbackType: $callbackType);
        $paymentId = self::getOrderReferenceFromCallbackModel(callbackModel: $callbackModel);

        if ($paymentId !== '') {
            $order = Database::getOrderByReference(orderReference: $paymentId);
            $callbackNote = sprintf(
                'Callback %s, for payment %s (order %s) received. Status/action: %s, trace: %s.',
                $callbackType->value,
                $paymentId,
                $order->get_id(),
                $callbackType === CallbackType::AUTHORIZATION ?
                    $callbackModel->status->value : $callbackModel->action->value,
                $callbackType === CallbackType::MANAGEMENT ? $callbackModel->actionId : '-'
            );

            Config::getLogger()->info(
                message: $callbackNote
            );
            Config::getLogger()->info(
                message: file_get_contents(filename: 'php://input')
            );

            // Order should be marked as callback-handled to simplify everything further.
            $order->add_order_note(note: $callbackNote);

            OrderStatus::setWcOrderStatus(
                order: $order,
                paymentId: $paymentId
            );

            return;
        }

        throw new CallbackException(message: 'Callback request parameters is incomplete or missing.', code: 408);
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
            CallbackType::AUTHORIZATION =>
            (new AuthorizationController())->getRequestModel(model: Authorization::class),
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
}
