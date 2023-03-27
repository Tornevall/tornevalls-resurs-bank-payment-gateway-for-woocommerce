<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback;

use Resursbank\Ecom\Exception\CallbackException;
use Resursbank\Ecom\Lib\Model\Callback\CallbackInterface;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Callback\Repository;
use Resursbank\Woocommerce\Modules\Callback\Callback as CallbackModule;
use Resursbank\Woocommerce\Modules\Callback\Controller\Authorization;
use Resursbank\Woocommerce\Modules\Callback\Controller\Management;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

use function is_string;

/**
 * Implementation of callback module.
 */
class Callback
{
    /**
     * Setup endpoint for incoming callbacks using the WC API.
     *
     * NOTE: we are required to use the API here because otherwise we will not
     * have access to our orders on frontend. If we attempt to use our regular
     * controller pattern orders are inaccessible.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function init(): void
    {
        add_action(
            hook_name: 'woocommerce_api_' . Route::ROUTE_PARAM,
            callback: 'Resursbank\Woocommerce\Modules\Callback\Callback::execute'
        );
    }

    /**
     * Performs callback processing.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function execute(): void
    {
        $type = $_GET['callback'] ?? '';

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            if ($type === '' || !is_string(value: $type)) {
                throw new CallbackException(message: 'Unknown callback type.');
            }

            Log::debug(message: "Executing $type callback.");

            $controller = $type === CallbackType::AUTHORIZATION->value ?
                new Authorization() :
                new Management();

            Route::respondWithExit(
                body: '',
                code: Repository::process(
                    callback: $controller->getRequestData(),
                    process: static function (
                        CallbackInterface $callback
                    ) use ($controller): void {
                        $order = CallbackModule::getOrder(
                            paymentId: $callback->getPaymentId()
                        );

                        $controller->updateOrderStatus(order: $order);
                    }
                )
            );
        } catch (Throwable $e) {
            Log::error(error: $e);
            Route::respondWithExit(
                body: $e->getMessage(),
                code: $e->getCode()
            );
        }
    }

    /**
     * @throws CallbackException
     */
    public static function getOrder(string $paymentId): WC_Order
    {
        $order = Metadata::getOrderByPaymentId(paymentId: $paymentId);

        if (!$order instanceof WC_Order) {
            throw new CallbackException(
                message: "Unable to find order matching $paymentId"
            );
        }

        return $order;
    }

    /**
     * Apply new status on order if the following conditions are met:
     *
     * 1. WC_Order may not have obtained a status which cannot be manipulated.
     * 2. Converted WC_Order status from the Resurs Bank payment most differ.
     */
    public static function updateOrderStatus(
        WC_Order $order,
        string $status
    ): void {
        if (
            $status === '' ||
            $order->has_status(status: $status) ||
            !$order->has_status(status: ['pending', 'processing', 'on-hold'])
        ) {
            return;
        }

        $order->update_status(
            new_status: $status,
            note: Translator::translate(phraseId: "payment-status-$status")
        );
    }
}
