<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */
/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace ResursBank\Module;

use Resursbank\Ecommerce\Types\CheckoutType;
use Resursbank\RBEcomPHP\ResursBank;
use Resursbank\Woocommerce\Modules\Ordermanagement\Module as OrdermanagementModule;
use TorneLIB\IO\Data\Arrays;

/**
 * Class Plugin Internal plugin handler.
 *
 * @package ResursBank\Module
 */
class PluginHooks
{
    public function __construct()
    {
        $this->getActions();
    }

    private function getActions(): void
    {
        add_action('woocommerce_order_refunded', [$this, 'refundResursOrder'], 10, 2);

        OrdermanagementModule::setupActions();
    }
}
