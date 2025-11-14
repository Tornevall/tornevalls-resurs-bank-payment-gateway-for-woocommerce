<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress;

/**
 * Implementation of get address widget in checkout.
 */
class GetAddress
{
    /**
     * Register frontend related actions and filters.
     */
    public static function init(): void
    {
        // Inject Get Address widget in blocked based checkout.
        add_filter(
            'the_content',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\Blocks\InjectFetchAddressWidget::exec'
        );

        // Inject Get Address widget in legacy checkout.
        add_filter(
            'woocommerce_before_checkout_form',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\Legacy\InjectFetchAddressWidget::exec'
        );
    }
}
