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
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
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
     * Register the WC API endpoint used for Resurs Bank callbacks.
     *
     * We use WooCommerce's `wc-api` route to handle callbacks because it gives us
     * reliable access to WooCommerce order APIs in a frontend request context.
     *
     * The same `wc-api` route may be used for other requests, so we only flag
     * requests that include the `callback` query parameter.
     *
     * `IS_RESURS_CALLBACK` is defined *before* the action is registered so other
     * components (request context checks, asset loading, payment field rendering)
     * can short-circuit early.
     *
     * Note: the callback type is passed via query string, so `$_GET` is enough.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function init(): void
    {
        // Flag as early as possible, for both GET/POST, before other hooks rely on it.
        $route = $_GET['wc-api'] ?? null;

        if (
            $route === Route::ROUTE_PARAM &&
            isset($_GET['callback']) &&
            !defined(constant_name: 'IS_RESURS_CALLBACK')
        ) {
            define(constant_name: 'IS_RESURS_CALLBACK', value: true);
        }

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
                $controller->getRequestData(),
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

                    try {
                        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                        $order->add_order_note($callback->getNote());
                    } catch (Throwable $e) {
                        // In case translations are lost in ecom transitions, we will
                        // push out the error message instead for which the phrase id will
                        // be displayed instead. If this occurs, and we do not do this,
                        // callbacks will be rejected with an error instead.
                        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                        $order->add_order_note($e->getMessage());
                    }

                    Status::update(order: $order);
                }
            )
        );
    }

    /**
     * Check that order is ready for callbacks.
     *
     * @throws HttpException
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     * @phpcs:ignoreFile CognitiveComplexity
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function checkIfReadyForCallback(WC_Order $order): void
    {
        /** @var WC_DateTime|null $dateCreated */
        $dateCreated = $order->get_date_created();

        if (!$dateCreated) {
            $dateCreated = new WC_DateTime();
        }

        $timeSince = time() - $dateCreated->format(format: 'U');
        $isRejected = false;

        try {
            $extendedOrderInfo = OrderManagement::getPayment(order: $order);
            $isRejected = $extendedOrderInfo->isRejected();
        } catch (Throwable) {
        }

        $rbCreated = Metadata::getOrderMeta(
            order: $order,
            key: Metadata::KEY_REPOSITORY_CREATED
        );

        // Applies to new orders for which we added this metadata key. If this value
        // for some reason is missing, we will keep using the order creation date.
        if ($rbCreated !== '' && (int)$rbCreated > 0) {
            $timeSince = time() - (int)$rbCreated;
        }

        if (
            $timeSince < self::MINIMUM_RESPONSE_DELAY &&
            !Metadata::isThankYouTriggered(order: $order)
        ) {
            throw new HttpException(
                sprintf(
                    'Order %s not ready for callbacks yet%s',
                    $order->get_id(),
                    $isRejected ? ' - Payment was rejected by Resurs.' : '.'
                ),
                503
            );
        }
    }
}
