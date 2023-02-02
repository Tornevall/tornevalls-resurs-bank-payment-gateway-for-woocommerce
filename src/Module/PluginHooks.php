<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */

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
