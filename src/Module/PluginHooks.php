<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */

/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace ResursBank\Module;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Ecommerce\Types\CheckoutType;
use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Modules\Ordermanagement\Module as OrdermanagementModule;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use RuntimeException;
use Throwable;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\Utils\WordPress as wpHelper;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Refund;
use WC_Product;
use WC_Tax;
use function count;
use function in_array;
use function is_array;

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
