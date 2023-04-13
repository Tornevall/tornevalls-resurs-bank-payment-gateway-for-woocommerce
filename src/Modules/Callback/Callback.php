<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback;

use Resursbank\Ecom\Exception\CallbackException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Model\Callback\Authorization as AuthorizationModel;
use Resursbank\Ecom\Lib\Model\Callback\CallbackInterface;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Http\ManagementController;
use Resursbank\Ecom\Module\Callback\Repository;
use Resursbank\Woocommerce\Modules\Callback\Callback as CallbackModule;
use Resursbank\Woocommerce\Modules\Order\Status;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Throwable;
use WC_DateTime;
use WC_Order;

use function is_string;

/**
 * Implementation of callback module.
 */
class Callback
{
    /**
     * Minimum delay before callbacks are handled if the order confirmation page has not been loaded by the customer.
     */
    private const MINIMUM_RESPONSE_DELAY = 60;

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
            'woocommerce_api_' . Route::ROUTE_PARAM,
            'Resursbank\Woocommerce\Modules\Callback\Callback::execute'
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

            self::respond(type: $type);
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
     * @throws ConfigException
     * @throws HttpException
     */
    private static function respond(
        string $type
    ): void {
        $controller = $type === CallbackType::AUTHORIZATION->value ?
            new AuthorizationController() :
            new ManagementController();

        Route::respondWithExit(
            body: '',
            code: Repository::process(
                callback: $controller->getRequestData(),
                process: static function (
                    CallbackInterface $callback
                ): void {
                    if (!($callback instanceof AuthorizationModel)) {
                        return;
                    }

                    $order = CallbackModule::getOrder(
                        paymentId: $callback->getPaymentId()
                    );

                    self::checkIfReadyForCallback(order: $order);

                    $order->add_order_note(note: $callback->getNote());

                    Status::update(order: $order);
                }
            )
        );
    }

    /**
     * Check that order is ready for callbacks.
     *
     * @throws HttpException
     */
    private static function checkIfReadyForCallback(WC_Order $order): void
    {
        /** @var WC_DateTime|null $dateCreated */
        $dateCreated = $order->get_date_created();

        if (!$dateCreated) {
            $dateCreated = new WC_DateTime();
        }

        $timeSince = time() - $dateCreated->format(format: 'U');

        if (
            $timeSince < self::MINIMUM_RESPONSE_DELAY &&
            !Metadata::isThankYouTriggered(order: $order)
        ) {
            throw new HttpException(
                message: 'Order not ready for callbacks yet',
                code: 503
            );
        }
    }
}
