<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Gateway\AdminPage;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\Exception\ExceptionHandler;
use WC_Session;
use function in_array;

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
     * @var WC_Session
     * @since 0.0.1.0
     */
    private static $session;

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
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getGateways($gateways)
    {
        if (is_admin()) {
            $gateways[] = ResursDefault::class;
        } else {
            $gateways = self::getGatewaysFromPaymentMethods($gateways);
        }
        return $gateways;
    }

    /**
     * @param $gateways
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getGatewaysFromPaymentMethods($gateways)
    {
        $methodList = Api::getPaymentMethods();

        foreach ($methodList as $methodClass) {
            $gatewayClass = new ResursDefault($methodClass);
            // Ask itself.
            if ($gatewayClass->is_available()) {
                $gateways[] = $gatewayClass;
            }
        }

        return $gateways;
    }

    /**
     * Self aware setup link.
     * @param $links
     * @param $file
     * @param null $section
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getPluginAdminUrl($links, $file, $section = null)
    {
        if (strpos($file, self::getBaseName()) !== false) {
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf(
                '<a href="%s?page=wc-settings&tab=%s&section=%s">%s</a>',
                admin_url(),
                Data::getPrefix('admin'),
                $section,
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
            throw new RuntimeException(
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
     * @throws ExceptionHandler
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminAfterOrderDetails($order = null)
    {
        if (!empty($order) &&
            Data::canHandleOrder($order->get_payment_method())
        ) {
            $orderData = Data::getOrderInfo($order);
            self::setOrderMetaInformation($orderData);
            if (WordPress::applyFilters('canDisplayOrderInfoAfterDetails', true)) {
                echo Data::getGenericClass()->getTemplate('adminpage_details.phtml', $orderData);
            }
            // Adaptable action. Makes it possible to go back to the prior "blue box view" from v2.x
            // if someone wants to create their own view.
            WordPress::doAction('showOrderDetails', $orderData);
        }
    }

    /**
     * @param $orderData
     * @throws ResursException
     * @throws ExceptionHandler
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function setOrderMetaInformation($orderData)
    {
        if (isset($orderData['ecom_short']) &&
            is_array($orderData['ecom_short']) &&
            count($orderData['ecom_short'])
        ) {
            $login = Data::getResursOption('login');
            $password = Data::getResursOption('password');
            if (!empty($password) &&
                !empty($login) &&
                Data::getResursOption('store_api_history') &&
                !Data::getOrderMeta('orderapi', $orderData['order'])) {
                Data::setLogInternal(
                    Data::LOG_NOTICE,
                    sprintf(
                        __('EComPHP data present, storing api meta to order %s.', 'trbwc'),
                        $orderData['order']->get_id()
                    )
                );

                // Set encrypted order meta data with api credentials that belongs to this order.
                Data::setOrderMeta(
                    $orderData['order'],
                    'orderapi',
                    Data::getCrypt()->aesEncrypt(
                        json_encode(
                            [
                                'l' => $login,
                                'p' => $password,
                                'e' => Data::getResursOption('environment'),
                            ]
                        )
                    )
                );
            }
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
     * @param $protected
     * @param $metaKey
     * @param $metaType
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getProtectedMetaData($protected, $metaKey, $metaType)
    {
        /** @noinspection NotOptimalRegularExpressionsInspection */
        // Order meta that is protected against editing.
        if (($metaType === 'post') && preg_match(sprintf('/^%s/i', Data::getPrefix()), $metaKey)) {
            $protected = true;
        }
        return $protected;
    }

    /**
     * @param null $order
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminAfterBilling($order = null)
    {
        if (!empty($order) &&
            WordPress::applyFilters('canDisplayOrderInfoAfterBilling', true) &&
            Data::canHandleOrder($order->get_payment_method())
        ) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getGenericClass()->getTemplate('adminpage_billing.phtml', $orderData);
        }
    }

    /**
     * @param null $order
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminAfterShipping($order = null)
    {
        if (!empty($order) &&
            WordPress::applyFilters('canDisplayOrderInfoAfterShipping', true) &&
            Data::canHandleOrder($order->get_payment_method())
        ) {
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
     * @param $scriptName
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getGenericLocalization($return, $scriptName)
    {
        if (is_checkout() && preg_match('/_checkout$/', $scriptName)) {
            $return[sprintf('%s_rco_suggest_id', Data::getPrefix())] = Api::getResurs()->getPreferredPaymentId();
            $return[sprintf('%s_checkout_type', Data::getPrefix())] = Data::getResursOption('checkout_type');
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
            'username',
            'isCurrentCredentials',
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

    /**
     * @param $key
     * @return array|mixed|string
     * @since 0.0.1.0
     */
    public static function getSessionValue($key)
    {
        $return = null;

        if (self::getSession()) {
            $return = WC()->session->get($key);
        } elseif (isset($_SESSION[$key])) {
            $return = $_SESSION[$key];
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    private static function getSession()
    {
        global $woocommerce;

        $return = false;
        if (isset($woocommerce->session) && !empty($woocommerce->session)) {
            $return = true;
        }

        return $return;
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is in checkout.
     * @since 0.0.1.0
     */
    public static function getBeforeCheckoutForm()
    {
        self::setCustomerCheckoutLocation(true);
    }

    /**
     * v3core: Checkout vs Cart Manipulation.
     * @param $customerIsInCheckout
     * @since 0.0.1.0
     */
    private static function setCustomerCheckoutLocation($customerIsInCheckout)
    {
        $sessionKey = 'customerWasInCheckout';
        Data::canLog(
            Data::CAN_LOG_JUNK,
            sprintf(
                __('Session value %s set to %s.', 'trbwc'),
                $sessionKey,
                $customerIsInCheckout ? 'true' : 'false'
            )
        );
        self::setSessionValue($sessionKey, $customerIsInCheckout);
    }

    /**
     * @param $key
     * @param $value
     * @since 0.0.1.0
     */
    public static function setSessionValue($key, $value)
    {
        if (self::getSession()) {
            WC()->session->set($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getWcApiUrl()
    {
        return sprintf('%s', WC()->api_request_url('ResursDefault'));
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is not in checkout.
     * @since 0.0.1.0
     */
    public static function getAddToCart()
    {
        self::setCustomerCheckoutLocation(false);
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is in checkout.
     * @param $fragments
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getReviewFragments($fragments)
    {
        self::setCustomerCheckoutLocation(true);

        return $fragments;
    }

    /**
     * @since 0.0.1.0
     */
    public static function getOrderReviewSettings()
    {
        // Rounding panic prevention.
        if (isset($_POST['payment_method']) && Data::isResursMethod($_POST['payment_method'])) {
            add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
        }
    }
}
