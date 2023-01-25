<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\CustomerType\Filter;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Woocommerce\Util\Admin;
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
        // Customer type scripts is only necessary outside admin, and if credentials are present.
        if (Admin::isAdmin()) {
            return;
        }

        self::enqueueScript();
    }

    /**
     * Prepare the script used to handle customer type on getAddress updates.
     *
     * @throws ConfigException
     */
    private static function enqueueScript(): void
    {
        add_action(
            hook_name: 'wp_enqueue_scripts',
            callback: static function (): void {
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
        );
    }
}
