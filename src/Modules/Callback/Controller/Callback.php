<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback\Controller;

use Resursbank\Ecom\Exception\CallbackException;
use Resursbank\Ecom\Lib\Model\Callback\CallbackInterface;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Http\ManagementController;
use Resursbank\Ecom\Module\Callback\Repository;
use ResursBank\Module\OrderStatus;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Throwable;

/**
 * Controller to handle incoming callbacks.
 */
class Callback
{
    /**
     * Process incoming callback.
     */
    public static function exec(
        CallbackType $type
    ): void {
        try {
            $controller = $type === CallbackType::AUTHORIZATION ?
                new AuthorizationController() :
                new ManagementController();

            Route::respondWithExit(
                body: '',
                code: Repository::process(
                    callback: $controller->getRequestData(),
                    process: static function (CallbackInterface $callback): void {
                        $order = Metadata::getOrderByPaymentId(
                            paymentId: $callback->getPaymentId()
                        );

                        if ($order === null) {
                            throw new CallbackException(
                                message: 'Unable to find order matching ' . $callback->getPaymentId()
                            );
                        }

                        $order->add_order_note(note: $callback->getNote());

                        OrderStatus::setWcOrderStatus(
                            order: $order,
                            paymentId: $callback->getPaymentId()
                        );
                    }
                )
            );
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }
}
