<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Callback\CallbackInterface;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Callback\Management;
use Resursbank\Ecom\Module\Action\Repository as ActionRepository;
use Resursbank\Woocommerce\Modules\Callback\Controller\Callback as CallbackController;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

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
     */
    public static function execute(): void
    {
        try {
            CallbackController::exec(
                type: CallbackType::from(
                    value: $_REQUEST['callback'] ?? ''
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
     * Add order notice on incoming callback.
     *
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
     */
    public static function addOrderNote(
        WC_Order $order,
        CallbackInterface $callback
    ): void {
        $note = $callback->getNote();

        if ($callback instanceof Management) {
            $note .= ' ' . sprintf(
                Translator::translate(
                    phraseId: 'callback-amount'
                ),
                ActionRepository::getAction(
                    paymentId: $callback->getPaymentId(),
                    actionId: $callback->actionId
                )->orderLines->getTotal()
            );
        }

        $order->add_order_note(note: $note);
    }
}
