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
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\Widget\GetPeriods\Js as GetPeriods;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Admin as AdminUtil;
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
            methodElementId: Reader::getOptionName(field: Field::PART_PAYMENT_METHOD_ID),
            periodElementId: Reader::getOptionName(field: Field::PART_PAYMENT_PERIOD)
        );

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            wp_register_script('partpayment-admin-scripts', false);
            wp_enqueue_script('partpayment-admin-scripts');
            wp_add_inline_script(
                'partpayment-admin-scripts',
                $periods->content,
                'before'
            );
            add_action('admin_enqueue_scripts', 'partpayment-admin-scripts');
        } catch (Throwable $exception) {
            Config::getLogger()->error(message: $exception);
        }
    }
}
