<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\AnnuityFactor\Widget\DurationByMonths;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Part payment admin functionality
 */
class Admin
{
    /**
     * @throws ConfigException
     */
    public static function setJs(): void
    {
        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $widget = new DurationByMonths(
                endpointUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT_ADMIN
                )
            );
            $url = Url::getScriptUrl(
                module: 'PartPayment',
                file: 'admin/updateAnnuityPeriod.js'
            );
            wp_enqueue_script(
                handle: 'partpayment-admin-scripts',
                src: $url,
                deps: ['jquery']
            );
            wp_add_inline_script(
                handle: 'partpayment-admin-scripts',
                data: $widget->getScript(),
                position: 'before'
            );
            add_action(
                hook_name: 'admin_enqueue_scripts',
                callback: 'partpayment-admin-scripts'
            );
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
