<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Callback\Authorization;
use Resursbank\Ecom\Lib\Model\Callback\Enum\Action;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Callback\Enum\Status;
use Resursbank\Ecom\Lib\Model\Callback\Management;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
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
     * @throws ConfigException
     * @throws FilesystemException
     * @throws HttpException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws CallbackException
     */
    public static function processCallback(CallbackType $callbackType): bool
    {
        $callbackModel = self::getCallbackModel(callbackType: $callbackType);
        $paymentId = self::getOrderReferenceFromCallbackModel(
            callbackModel: $callbackModel
        );
        if ($paymentId !== '') {
            $order = Database::getOrderByReference(orderReference: $paymentId);
            // Currently not using getPayment as it is not yet clarified.
            // @todo Solve the questions around order statuses before running this check.
            //self::getStatusFromResurs($paymentId);

            return self::setWcOrderStatus(
                order: $order,
                wcResursStatus: self::getStatusByPayload(
                    callbackType: $callbackType,
                    callbackModel: $callbackModel
                )
            );
        }

        throw new CallbackException(message: 'Could not handle callback.', code: 408);
    }

    /**
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
     * @param string $wcResursStatus Status returned from Resurs, in WooCommerce terms.
     * @return bool
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function setWcOrderStatus(WC_Order $order, string $wcResursStatus): bool
    {
        if ($order->get_status() !== $wcResursStatus) {
            // Do not change status if order is cancelled, due to the fact that order reservations and stock may
            // have changed since the order was cancelled.
            if ($order->get_status() !== 'cancelled') {
                $order->update_status(
                    new_status: $wcResursStatus,
                    note: Translator::translate(
                        phraseId: 'updated-status-by-callback'
                    )
                );
                if ($wcResursStatus === 'completed') {
                    // Trigger internal functions and let others handle hooks related to order completion.
                    $order->payment_complete();
                }
            }
        }

        return $order->get_status() === $wcResursStatus;
    }

    /**
     * @param CallbackType $callbackType
     * @param Authorization|Management $callbackModel
     * @return string
     */
    private static function getStatusByPayload(
        CallbackType $callbackType,
        Authorization|Management $callbackModel
    ): string {
        return match ($callbackType) {
            CallbackType::AUTHORIZATION => self::getAuthorizationStatusByCallbackPayload(status: $callbackModel->status),
            CallbackType::MANAGEMENT => self::getManagementStatusByCallbackPayload(action: $callbackModel->action),
        };
    }

    /**
     * @param Status $status
     * @return string
     * @todo Centralize this with ecom2, since management actions are based on the same setup
     */
    private static function getAuthorizationStatusByCallbackPayload(Status $status): string
    {
        return match ($status) {
            Status::FROZEN => 'on-hold',
            Status::AUTHORIZED => 'processing',
            Status::CAPTURED => 'completed',
            default => 'failed',
        };
    }

    /**
     * @param Action $action
     * @return string
     * @todo Centralize this with ecom2, since management actions are based on the same setup
     */
    private static function getManagementStatusByCallbackPayload(Action $action): string
    {
        return match ($action) {
            Action::CANCEL => 'cancelled',
            Action::CAPTURE => 'completed',
            Action::REFUND => 'refunded',
            default => 'failed',
        };
    }

    /**
     * @param string $paymentId
     * @return void
     * @todo This is not yet fully implemented but left here for future use.
     */
    private static function getStatusFromResurs(string $paymentId): void
    {
        //$resursPayment = Repository::get(paymentId: $paymentId);
        //return $resursPayment;
    }
}