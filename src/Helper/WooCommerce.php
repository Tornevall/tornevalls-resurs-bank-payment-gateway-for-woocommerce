<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Gateway\AdminPage;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursException;
use stdClass;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooCommerce WooCommerce related actions.
 * @package ResursBank
 * @since 0.0.1.0
 */
class WooCommerce
{
    /**
     * @var $basename
     * @since 0.0.1.0
     */
    private static $basename;

    /**
     * By this plugin lowest required woocommerce version.
     * @var string
     * @since 0.0.1.0
     */
    private static $requiredVersion = '3.4.0';

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getActiveState()
    {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins')),
            true
        );
    }

    /**
     * @param $settings
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getSettingsPages($settings)
    {
        if (is_admin()) {
            $settings[] = new AdminPage();
        }

        return $settings;
    }

    /**
     * @param $gateways
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getGateway($gateways)
    {
        $gateways[] = ResursDefault::class;
        return $gateways;
    }

    /**
     * Self aware setup link.
     * @param $links
     * @param $file
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getPluginAdminUrl($links, $file)
    {
        if (strpos($file, self::getBaseName()) !== false) {
            $links[] = sprintf(
                '<a href="%s?page=wc-settings&tab=%s">%s</a>',
                admin_url(),
                Data::getPrefix('admin'),
                __(
                    'Settings'
                )
            );
        }
        return $links;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getBaseName()
    {
        if (empty(self::$basename)) {
            self::$basename = trim(plugin_basename(Data::getGatewayPath()));
        }

        return self::$basename;
    }

    /**
     * @param null $testException
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testRequiredVersion($testException = null)
    {
        if ((bool)$testException || version_compare(self::getWooCommerceVersion(), self::$requiredVersion, '<')) {
            throw new Exception(
                'Your WooCommerce release are too old. Please upgrade.',
                500
            );
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getWooCommerceVersion()
    {
        global $woocommerce;

        $return = null;

        if (isset($woocommerce)) {
            $return = $woocommerce->version;
        }

        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getRequiredVersion()
    {
        return self::$requiredVersion;
    }

    /**
     * @param mixed $order
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getAdminAfterOrderDetails($order = null)
    {
        if (!empty($order) && Data::canHandleOrder($order->get_payment_method())) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getGenericClass()->getTemplate('adminpage_details.phtml', $orderData);
        }
    }

    /**
     * @param $methodName
     * @return bool
     * @since 0.0.1.0
     */
    public static function getIsOldMethod($methodName)
    {
        $return = false;
        if (strncmp($methodName, 'resurs_bank_', 12) === 0) {
            $return = true;
        }
        return $return;
    }

    /**
     * @param null $order
     * @throws ResursException
     */
    public static function getAdminAfterBilling($order = null)
    {
        if (!empty($order) && Data::canHandleOrder($order->get_payment_method())) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getGenericClass()->getTemplate('adminpage_billing.phtml', $orderData);
        }
    }

    /**
     * @param null $order
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getAdminAfterShipping($order = null)
    {
        if (!empty($order) && Data::canHandleOrder($order->get_payment_method())) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getGenericClass()->getTemplate('adminpage_shipping.phtml', $orderData);
        }
    }

    /**
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getFormattedPaymentData($return)
    {
        $return['customer_billing'] = self::getAdminCustomerAddress(
            $return['ecom']->customer->address
        );
        $return['customer_shipping'] = isset($return['ecom']->deliveryAddress) ?
            self::getAdminCustomerAddress($return['ecom']->deliveryAddress) : [];

        return $return;
    }

    /**
     * @param stdClass $ecomCustomer
     * @return array
     * @since 0.0.1.0
     */
    private static function getAdminCustomerAddress($ecomCustomer)
    {
        $return = [
            'fullName' => !empty($ecomCustomer->fullName) ? $ecomCustomer->fullName : $ecomCustomer->firstName,
            'addressRow1' => $ecomCustomer->addressRow1,
            'postal' => sprintf('%s  %s', $ecomCustomer->postalCode, $ecomCustomer->postalArea),
            'country' => $ecomCustomer->country,
        ];
        if (empty($ecomCustomer->fullName)) {
            $return['fullName'] .= ' ' . $ecomCustomer->lastName;
        }
        if (isset($ecomCustomer->addressRow2) && !empty($ecomCustomer->addressRow2)) {
            $return['addressRow1'] .= "\n" . $ecomCustomer->addressRow2;
        }

        return $return;
    }

    /**
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getPaymentInfoDetails($return)
    {
        $return['ecom_short'] = [];
        $purge = [
            'id',
            'paymentDiffs',
            'customer',
            'deliveryAddress',
            'paymentMethodId',
            'paymentMethodName',
            'paymentMethodType',
            'totalBonusPoints',
            'cached',
            'metaData',
            'totalAmount',
            'limit',
        ];
        if (isset($return['ecom'])) {
            $purgedEcom = (array)$return['ecom'];
            foreach ($purgedEcom as $key => $value) {
                if (in_array($key, $purge, true)) {
                    unset($purgedEcom[$key]);
                }
            }
            $return['ecom_short'] = $purgedEcom;
        }
        return $return;
    }
}
