<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CallbackTypeException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Callback\Authorization;
use Resursbank\Ecom\Lib\Model\Callback\Enum\Action;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Callback\Enum\Status;
use Resursbank\Ecom\Lib\Model\Callback\Management;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Woocommerce\Util\Database;

/**
 * Callback handling by automation.
 */
class Callback
{
    /**
     * Callback automation goes here.
     * @param CallbackType $callbackType
     * @return bool
     * @throws CallbackTypeException
     * @throws HttpException
     */
    public static function processCallback(CallbackType $callbackType): bool
    {
        if ($callbackType === CallbackType::AUTHORIZATION) {
            return self::processAuthorization();
        } elseif ($callbackType === CallbackType::MANAGEMENT) {
            return self::processManagement();
        }

        throw new CallbackTypeException(message: 'Unknown callback type.');
    }

    /**
     * @throws HttpException
     * @throws Exception
     */
    private static function processAuthorization(): bool
    {
        $success = false;

        /** @var Authorization $callbackModel */
        $callbackModel = (new AuthorizationController())->getRequestModel(model: Authorization::class);

        // If order fails to be fetched, exceptions thrown here will be catched in the primary method,
        // so it will show a proper error to the sender.
        $order = Database::getOrderByReference($callbackModel->paymentId);

        // @todo This request should be based on a getPayment rather than the received callbacks.
        // @todo By doing this, we'll get a secure layer between the callback server and the shop.
        $resursStatus = self::getAuthorizationStatusByCallbackPayload(status: $callbackModel->status);

        // @todo Status setter should be centralized.
        if ($order->get_status() === $resursStatus) {
            // Do not change status twice if already set, just report received-success back.
            $success = true;
        } else {
            // Do not change status if order is cancelled, due to the fact that order reservations and stock may
            // have changed since the order was cancelled.
            if ($order->get_status() !== 'cancelled') {
                $success = $order->update_status(
                    $resursStatus, note: Translator::translate(phraseId: 'updated-status-by-callback')
                );
                if ($resursStatus === 'completed') {
                    // Trigger internal functions and let others handle hooks related to order completion.
                    $order->payment_complete();
                }
            }
        }

        return $success;
    }

    /**
     * @throws HttpException
     * @throws Exception
     */
    public static function processManagement(): bool
    {
        $success = false;

        /** @var Management $callbackModel */
        $callbackModel = (new AuthorizationController())->getRequestModel(model: Management::class);
        $order = Database::getOrderByReference($callbackModel->paymentId);

        // @todo This request should be based on a getPayment rather than the received callbacks.
        // @todo By doing this, we'll get a secure layer between the callback server and the shop.
        $resursStatus = self::getManagementStatusByCallbackPayload(action: $callbackModel->action);

        // @todo Status setter should be centralized.
        if ($order->get_status() === $resursStatus) {
            // Do not change status twice if already set, just report received-success back.
            $success = true;
        } else {
            // Do not change status if order is cancelled, due to the fact that order reservations and stock may
            // have changed since the order was cancelled.
            if ($order->get_status() !== 'cancelled') {
                $success = $order->update_status(
                    $resursStatus, note: Translator::translate(phraseId: 'updated-status-by-callback')
                );
                if ($resursStatus === 'completed') {
                    // Trigger internal functions and let others handle hooks related to order completion.
                    $order->payment_complete();
                }
            }
        }

        return $success;
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
}