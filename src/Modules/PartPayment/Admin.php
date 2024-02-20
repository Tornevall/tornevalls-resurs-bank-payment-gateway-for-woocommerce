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
use Resursbank\Woocommerce\Util\Admin as AdminUtil;
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
        // End execution if not in 'partpayment' section. Allow script load regardless of enablement.
        if (!AdminUtil::isSection(sectionName: 'partpayment')) {
            return;
        }

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
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
