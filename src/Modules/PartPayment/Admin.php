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
        $asd = 'asd';
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
                'partpayment-admin-scripts',
                $url,
                ['jquery']
            );
            wp_add_inline_script(
                'partpayment-admin-scripts',
                $widget->getScript(),
                'before'
            );
            add_action('admin_enqueue_scripts', 'partpayment-admin-scripts');



            $url2 = Url::getScriptUrl(
                module: 'PartPayment',
                file: 'admin/test.js'
            );
            wp_register_script('rb-test-admin-scripts', $url2, ['jquery']);
            wp_enqueue_script('rb-test-admin-scripts');
            add_action('admin_enqueue_scripts', 'rb-test-admin-scripts');
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
