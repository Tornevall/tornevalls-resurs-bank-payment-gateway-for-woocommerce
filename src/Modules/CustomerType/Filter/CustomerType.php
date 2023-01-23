<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\CustomerType\Filter;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

/**
 * Generates scripts that syncs customer types with getAddress-actions.
 */
class CustomerType
{
    /**
     * @throws ConfigException
     */
    public static function setup(): void
    {
        self::enqueueScript();
        self::enqueueAjaxLocalization();
    }

    /**
     * Prepare the script used to handle customer type on getAddress updates.
     */
    private static function enqueueScript(): void
    {
        wp_enqueue_script(
            handle: 'rb-set-customertype',
            src: Url::getScriptUrl(
                module: 'CustomerType',
                file: 'customertype.js'
            ),
            deps: [
                'jquery',
            ]
        );
    }

    /**
     * Localize data required for customerType-pushing to work.
     *
     * @throws ConfigException
     */
    private static function enqueueAjaxLocalization(): void
    {
        try {
            wp_localize_script(
                handle: 'rb-set-customertype',
                object_name: 'rbCustomerTypeData',
                l10n: [
                    'currentCustomerType' => WcSession::getCustomerType(),
                    'apiUrl' => Route::getUrl(
                        route: Route::ROUTE_SET_CUSTOMER_TYPE
                    ),
                ]
            );
        } catch (Throwable $e) {
            Config::getLogger()->error(message: $e);
        }
    }
}
