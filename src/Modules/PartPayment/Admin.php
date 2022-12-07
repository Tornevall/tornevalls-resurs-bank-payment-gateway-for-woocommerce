<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Module\AnnuityFactor\Widget\DurationByMonths;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;

class Admin
{
    public function __construct()
    {

    }

    /**
     * @return void
     * @throws \Resursbank\Ecom\Exception\ConfigException
     */
    public static function setJs(): void
    {
        try {
            $widget = new DurationByMonths(endpointUrl: Route::getUrl(route: Route::ROUTE_PART_PAYMENT_ADMIN));
            $url = Url::getPluginUrl(
                path: RESURSBANK_MODULE_DIR_NAME . '/Modules',
                file: 'src/Modules/PartPayment/resources/js/admin/updateAnnuityPeriod.js'
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
                'admin_enqueue_scripts',
                'partpayment-admin-scripts'
            );
        } catch (Exception $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}