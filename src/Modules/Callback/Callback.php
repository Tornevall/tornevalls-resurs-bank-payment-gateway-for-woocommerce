<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback;

use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Woocommerce\Modules\Callback\Controller\Callback as CallbackController;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Throwable;

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
            callback: static function (): void {
                try {
                    CallbackController::exec(
                        type: CallbackType::from(
                            value: $_REQUEST['callback'] ?? ''
                        )
                    );
                } catch (Throwable $e) {
                    Log::error(error: $e);
                }
            }
        );
    }
}
