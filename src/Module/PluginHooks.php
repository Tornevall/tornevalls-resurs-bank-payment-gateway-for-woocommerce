<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use Resursbank\Woocommerce\Modules\Ordermanagement\Module as OrdermanagementModule;

/**
 * Class Plugin Internal plugin handler.
 *
 * @package ResursBank\Module
 */
class PluginHooks
{
    public function __construct()
    {
        OrdermanagementModule::setupActions();
    }
}
