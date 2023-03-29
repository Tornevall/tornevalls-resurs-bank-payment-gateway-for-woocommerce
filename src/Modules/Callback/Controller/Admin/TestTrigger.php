<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Callback\Controller\Admin;

use Resursbank\Ecom\Exception\CallbackException;
use Resursbank\Ecom\Exception\PermissionException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\TestStatus;
use Resursbank\Ecom\Module\Callback\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Test callback connectivity.
 */
class TestTrigger
{
    public static function exec(): void
    {
        try {
            if (!Admin::isAdmin()) {
                throw new PermissionException(message: 'Must be admin.');
            }

            $response = Repository::triggerTest(
                url: Route::getUrl(route: Route::ROUTE_TEST_CALLBACK_RECEIVED)
            );

            if ($response->status !== TestStatus::OK) {
                throw new CallbackException(
                    message: 'Test callback failed with status ' . $response->status->value . " ($response->code)"
                );
            }

            MessageBag::addSuccess(
                message: Translator::translate(
                    phraseId: 'callback-test-succeeded'
                )
            );
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: Translator::translate(phraseId: 'callback-test-failed')
            );
        }
    }
}
