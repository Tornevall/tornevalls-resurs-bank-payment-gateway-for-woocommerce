<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Gateway\AdminPage;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\Exception\ExceptionHandler;
use WC_Order;
use WC_Session;
use function in_array;

/**
 * Class WooCommerce WooCommerce related actions.
 *
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
     *
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
     *
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
            $orderData['ecom_meta'] = [];
            if (!isset($orderData['ecom'])) {
                $orderData['ecom'] = [];
                $orderData['ecom_short'] = [];
            }
            if (isset($orderData['meta']) && is_array($orderData['meta'])) {
                $orderData['ecom_short'] = self::getMetaDataFromOrder($orderData['ecom_short'], $orderData['meta']);
            }
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
                        __('EComPHP data present. Saving metadata for order %s.', 'trbwc'),
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
     * @param $ecomHolder
     * @param $metaArray
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getMetaDataFromOrder($ecomHolder, $metaArray)
    {
        $metaPrefix = Data::getPrefix();
        /** @var array $ecomMetaArray */
        $ecomMetaArray = [];
        foreach ($metaArray as $metaKey => $metaValue) {
            if (preg_match(sprintf('/^%s/', $metaPrefix), $metaKey)) {
                $metaKey = (string)preg_replace(sprintf('/^%s_/', $metaPrefix), '', $metaKey);
                if (is_array($metaValue) && count($metaValue) === 1) {
                    $metaValue = array_pop($metaValue);
                }
                if (is_string($metaValue)) {
                    $ecomMetaArray[$metaKey] = $metaValue;
                }
            }
        }
        return array_merge($ecomHolder, self::getPurgedMetaData($ecomMetaArray));
    }

    /**
     * @param $metaDataContainer
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getPurgedMetaData($metaDataContainer)
    {
        $purgeArray = [
            'orderSigningPayload',
            'orderapi',
            'apiDataId',
            'bookPaymentStatus',
        ];
        // Not necessary for customer to view.
        $metaPrefix = Data::getPrefix();
        foreach ($purgeArray as $purgeKey) {
            if (isset($metaDataContainer[$purgeKey])) {
                unset($metaDataContainer[$purgeKey]);
            }
            $prefixed = sprintf('%s_%s', $metaPrefix, $purgeKey);
            if (isset($metaDataContainer[$prefixed])) {
                unset($metaDataContainer[$prefixed]);
            }
        }
        return $metaDataContainer;
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
            'paymentDiffs',
            'customer',
            'deliveryAddress',
            'paymentMethodId',
            'totalBonusPoints',
            'metaData',
            'username',
            'isCurrentCredentials',
            'environment',
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
     *
     * @since 0.0.1.0
     */
    public static function getBeforeCheckoutForm()
    {
        self::setCustomerCheckoutLocation(true);
    }

    /**
     * v3core: Checkout vs Cart Manipulation.
     *
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
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getHandledCallback()
    {
        $order = null;
        $code = 202;
        $responseString = 'Accepted';
        $getConfirmedSalt = self::getConfirmedSalt();
        $logNotice = self::getCallbackLogNotice($getConfirmedSalt);

        // This should be both logged as entries and in order.
        Data::setLogNotice($logNotice);
        $callbackType = self::getRequest('c');
        $replyArray = [
            'aliveConfirm' => true,
            'actual' => $callbackType,
        ];

        // If there is a payment, there must be a digest.
        if (!empty(self::getRequest('p'))) {
            $orderId = Data::getOrderByEcomRef(self::getRequest('p'));

            if ($orderId) {
                $order = new WC_Order($orderId);
                $order->add_order_note(
                    $logNotice
                );
            }

            if ($getConfirmedSalt && $orderId) {
                try {
                    self::getUpdatedOrderByCallback(self::getRequest('p'), $orderId, $order);
                } catch (Exception $e) {
                    $code = $e->getCode();
                    $responseString = $e->getMessage();
                    Data::setLogException($e);
                }
            } else {
                $code = 406; // Not acceptable
                $responseString = 'Digest rejected';
            }
            $replyArray['digestCode'] = $code;
        } elseif ($callbackType === 'TEST') {
            Data::setResursOption('resurs_callback_test_response', time());
            // There are not digest codes available in this state so we should throw the callback handler
            // a success regardless.
            $replyArray['digestCode'] = '200';
        }

        self::reply(
            $replyArray,
            $code,
            $responseString
        );
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getConfirmedSalt()
    {
        return Api::getResurs()->getValidatedCallbackDigest(
            self::getRequest('p'),
            self::getCurrentSalt(),
            self::getRequest('d'),
            self::getRequest('r')
        );
    }

    /**
     * @param $key
     * @param bool $post_data
     * @return string
     * @since 0.0.1.0
     */
    public static function getRequest($key, $post_data = null)
    {
        $return = isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;

        if (null === $return && (bool)$post_data && isset($_REQUEST['post_data'])) {
            parse_str($_REQUEST['post_data'], $newPostData);
            if (is_array($newPostData) && isset($newPostData[$key])) {
                $return = $newPostData[$key];
            }
        }

        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private static function getCurrentSalt()
    {
        return (string)Data::getResursOption('salt');
    }

    /**
     * @param $getConfirmedSalt
     * @return string
     * @since 0.0.1.0
     */
    private static function getCallbackLogNotice($getConfirmedSalt)
    {
        return sprintf(
            __(
                'Callback received from Resurs Bank: %s (Digest Status: %s, External ID: %s, Internal ID: %d).',
                'trbwc'
            ),
            self::getRequest('c'),
            $getConfirmedSalt ? __('Valid', 'trbwc') : __('Invalid', 'trbwc'),
            self::getRequest('p'),
            Data::getOrderByEcomRef(self::getRequest('p'))
        );
    }

    /**
     * @param $paymentId
     * @param $orderId
     * @param $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getUpdatedOrderByCallback($paymentId, $orderId, $order)
    {
        if ($orderId) {
            self::setOrderStatusByCallback(Api::getResurs()->getOrderStatusByPayment($paymentId), $order);
        }
    }

    /**
     * @param $ecomOrderStatus
     * @param WC_Order $order
     * @since 0.0.1.0
     */
    private static function setOrderStatusByCallback($ecomOrderStatus, $order)
    {
        switch (true) {
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_AUTOMATICALLY_DEBITED:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            case $ecomOrderStatus & RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_MANUAL_INSPECTION:
                self::setOrderStatusWithNotice($order, $ecomOrderStatus);
                break;
            default:
        }
    }

    /**
     * @param WC_Order $order
     * @param $ecomOrderStatus
     * @return mixed
     * @since 0.0.1.0
     */
    private static function setOrderStatusWithNotice($order, $ecomOrderStatus)
    {
        return $order->update_status(
            self::getOrderStatuses($ecomOrderStatus),
            sprintf(
                __('Resurs Bank updated order status to %s.', 'trbwc'),
                self::getOrderStatuses($ecomOrderStatus)
            )
        );
    }

    /**
     * @param null $key
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getOrderStatuses($key = null)
    {
        $return = WordPress::applyFilters('getOrderStatuses', [
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING => 'processing',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED => 'refunded',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED => 'completed',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING => 'on-hold',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED => 'cancelled',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_STATUS_COULD_NOT_BE_SET => 'on-hold',
            RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_MANUAL_INSPECTION => 'on-hold',
        ]);
        if (isset($key, $return[$key])) {
            return $return[$key];
        }
        return $return;
    }

    /**
     * @param array $out
     * @param int $code
     * @param string $httpString
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private static function reply($out, $code = 202, $httpString = 'Accepted')
    {
        $sProtocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        $replyString = sprintf('%s %d %s', $sProtocol, $code, $httpString);
        header('Content-type: application/json');
        header($replyString, true, $code);
        echo json_encode($out);
        exit;
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is not in checkout.
     *
     * @since 0.0.1.0
     */
    public static function getAddToCart()
    {
        self::setCustomerCheckoutLocation(false);
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is in checkout.
     *
     * @param $fragments
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getReviewFragments($fragments)
    {
        $fragments['#rbGetAddressFields'] = FormFields::getGetAddressForm(null, true);
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
