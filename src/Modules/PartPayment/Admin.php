<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Module\AnnuityFactor\Widget\GetPeriods;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Util\Admin as AdminUtil;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Part payment admin functionality
 */
class Admin
{
    /**
     * @throws ConfigException
     * @throws FilesystemException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function setJs(): void
    {
        // End execution if not in 'partpayment' section. Allow script load regardless of enablement.
        if (!AdminUtil::isSection(sectionName: 'partpayment')) {
            return;
        }

        $periods = new GetPeriods(
            storeId: StoreId::getData(),
            methodElementId: 'resursbank_part_payment_payment_method',
            periodElementId: 'resursbank_part_payment_period'
        );

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            wp_register_script('partpayment-admin-scripts', false);
            wp_enqueue_script('partpayment-admin-scripts');
            wp_add_inline_script(
                'partpayment-admin-scripts',
                $periods->js,
                'before'
            );
            add_action('admin_enqueue_scripts', 'partpayment-admin-scripts');

            wp_register_script(
                'rb-store-admin-scripts-load',
                Url::getResourceUrl(
                    module: 'PartPayment',
                    file: 'rb-part-payment-admin.js'
                )
            );

            wp_enqueue_script(
                'rb-store-admin-scripts-load',
                Url::getResourceUrl(
                    module: 'PartPayment',
                    file: 'rb-part-payment-admin.js'
                ),
                ['jquery']
            );
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
