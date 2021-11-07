<?php

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecommerce\Types\OrderStatus;
use ResursBank\Gateway\AdminPage;
use ResursBank\Gateway\ResursCheckout;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursBank\Service\OrderStatus as OrderStatusHandler;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\Exception\ExceptionHandler;
use WC_Order;
use WC_Session;
use function in_array;
use function is_string;

/**
 * Class WooCommerce WooCommerce related actions.
 *
 * @package ResursBank
 * @since 0.0.1.0
 */
class WooCommerce
{
    /**
     * Key in session to mark whether customer is in checkout or not. This is now global since RCO will
     * set that key on the backend request.
     *
     * @var string
     * @since 0.0.1.0
     */
    public static $inCheckoutKey = 'customerWasInCheckout';
    /**
     * @var WC_Session
     * @since 0.0.1.0
     */
    private static $session;
    /** @var array getAddress form fields translated into wooCommerce address data */
    private static $getAddressTranslation = [
        'first_name' => 'firstName',
        'last_name' => 'lastName',
        'address_1' => 'addressRow1',
        'address_2' => 'addressRow2',
        'city' => 'postalArea',
        'postcode' => 'postalCode',
        'country' => 'country',
    ];
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
     * Handle payment method gateways.
     *
     * @param $gateways
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getGatewaysFromPaymentMethods($gateways)
    {
        $methodList = Api::getPaymentMethods();
        $currentCheckoutType = Data::getCheckoutType();
        if ((bool)WordPress::applyFiltersDeprecated('temporary_disable_checkout', null) ||
            $currentCheckoutType !== ResursDefault::TYPE_RCO
        ) {
            // For simplified flow and hosted flow, we create individual class modules for all payment methods
            // that has been received from the getPaymentMethods call.
            foreach ($methodList as $methodClass) {
                $gatewayClass = new ResursDefault($methodClass);
                // Ask itself if it is enabled.
                if ($gatewayClass->is_available()) {
                    $gateways[] = $gatewayClass;
                }
            }
        } else {
            // In RCO mode, we don't have to handle all separate payment methods as a gateway since
            // the iframe keeps track of them, so in this case will create a smaller class gateway through the
            // ResursCheckout module.
            $gatewayClass = new ResursDefault(new ResursCheckout());
            $gateways[] = $gatewayClass;
        }

        return $gateways;
    }

    /**
     * @param $gateways
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAvailableGateways($gateways)
    {
        if (!is_admin()) {
            $customerCountry = Data::getCustomerCountry();

            if ($customerCountry !== get_option('woocommerce_default_country')) {
                foreach ($gateways as $gatewayName => $gatewayClass) {
                    if ($gatewayClass->getType() === 'PAYMENT_PROVIDER' &&
                        (bool)preg_match('/card/i', $gatewayClass->getSpecificType())
                    ) {
                        continue;
                    }
                    unset($gateways[$gatewayName]);
                }
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
        self::getAdminAfterOldCheck($order);

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
                $orderData['ecom_short']['ecom_had_reference_problems'] = self::getEcomHadProblemsInfo($orderData);
                echo Data::getGenericClass()->getTemplate('adminpage_details.phtml', $orderData);
            }
            // Adaptable action. Makes it possible to go back to the prior "blue box view" from v2.x
            // if someone wants to create their own view.
            WordPress::doAction('showOrderDetails', $orderData);
        }
    }

    /**
     * @param $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getAdminAfterOldCheck($order)
    {
        if ($order->meta_exists('resursBankPaymentFlow') &&
            !Data::hasOldGateway() &&
            !Data::getResursOption('deprecated_interference')
        ) {
            echo Data::getGenericClass()->getTemplate(
                'adminpage_woocommerce_version22',
                [
                    'wooPlug22VersionInfo' => __(
                        'This order has not been created by this plugin and the other plugin is currently unavailable.',
                        'trbwc'
                    ),
                ]
            );
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
                    Data::setEncryptData(
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
                if (is_array($metaValue)) {
                    if (count($metaValue) === 1) {
                        $metaValue = array_pop($metaValue);
                    }
                }
                if (is_string($metaValue) || is_array($metaValue)) {
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
     * @param $orderData
     * @since 0.0.1.0
     */
    private static function getEcomHadProblemsInfo($orderData)
    {
        $return = false;
        if (isset($orderData['ecom_had_reference_problems']) && $orderData['ecom_had_reference_problems']) {
            $return = sprintf(
                __(
                    'This payment is marked with reference problems. This means that there might have been ' .
                    'problems when the payment was executed and tried to update the payment reference (%s) to a new ' .
                    'id (%s). You can check the UpdatePaymentReference values for errors.',
                    'trbwc'
                ),
                isset($orderData['resurs_secondary']) ? $orderData['resurs_secondary'] : '[missing reference]',
                $orderData['resurs']
            );
        }

        return $return;
    }

    /**
     * Create a mocked moment if test and allowed mocking is enabled.
     * @param $mock
     * @since 0.0.1.0
     */
    public static function applyMock($mock)
    {
        if (Data::canMock($mock)) {
            WordPress::doAction(
                sprintf('mock%s', ucfirst($mock)),
                null
            );
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
        // This won't work if the payment is not at Resurs yet.
        if (isset($return['ecom'])) {
            $return['customer_billing'] = self::getAdminCustomerAddress(
                $return['ecom']->customer->address
            );

            $return['customer_shipping'] = isset($return['ecom']->deliveryAddress) ?
                self::getAdminCustomerAddress($return['ecom']->deliveryAddress) : [];
        }

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
            $return[sprintf('%s_checkout_type', Data::getPrefix())] = Data::getCheckoutType();
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
     * v3core: Checkout vs Cart Manipulation - A moment when customer is in checkout.
     *
     * @since 0.0.1.0
     */
    public static function setIsInCheckout()
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
        Data::canLog(
            Data::CAN_LOG_JUNK,
            sprintf(
                __('Session value %s set to %s.', 'trbwc'),
                self::$inCheckoutKey,
                $customerIsInCheckout ? 'true' : 'false'
            )
        );
        self::setSessionValue(self::$inCheckoutKey, $customerIsInCheckout);
    }

    /**
     * @param $key
     * @param $value
     * @since 0.0.1.0
     */
    public static function setSessionValue($key, $value)
    {
        Data::canLog(
            Data::CAN_LOG_JUNK,
            sprintf(
                '%s, %s=%s',
                __FUNCTION__,
                $key,
                is_string($value) ? $value : print_r($value, true)
            )
        );
        if (self::getSession()) {
            WC()->session->set($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
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
        $pRequest = self::getRequest('p');
        if (!empty($pRequest)) {
            $orderId = Data::getOrderByEcomRef(self::getRequest('p'));

            if ($orderId) {
                $order = new WC_Order($orderId);
                self::setOrderNote(
                    new WC_Order($orderId),
                    $logNotice
                );
            }

            self::getHandledCallbackNullOrder($order, $pRequest);

            try {
                Data::setOrderMeta(
                    $order,
                    sprintf('callback_%s_receive', $callbackType),
                    strftime('%Y-%m-%d %H:%M:%S', time()),
                    true,
                    true
                );
            } catch (Exception $e) {
                Data::setLogException(
                    $e
                );
            }

            if ($getConfirmedSalt && $orderId) {
                try {
                    self::getUpdatedOrderByCallback(self::getRequest('p'), $orderId, $order);
                    self::setSigningMarked($orderId, $callbackType);
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

        Data::setLogNotice(
            sprintf(
                __('Callback (%s) Handling for %s finished.', 'trbwc'),
                $callbackType,
                $pRequest
            )
        );

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
     * Set order note, but prefixed by plugin name.
     *
     * @param $order
     * @param $orderNote
     * @param int $is_customer_note
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function setOrderNote($order, $orderNote, $is_customer_note = 0)
    {
        $properOrder = self::getProperOrder($order, 'order');
        if (method_exists($properOrder, 'get_id') && $properOrder->get_id()) {
            Data::canLog(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __(
                        'setOrderNote for %s: %s'
                    ),
                    $properOrder->get_id(),
                    $orderNote
                )
            );

            self::getProperOrder($order, 'order')->add_order_note(
                self::getOrderNotePrefixed($orderNote),
                $is_customer_note
            );
        }
    }

    /**
     * Centralized order retrieval.
     * @param $orderContainer
     * @param $returnAs
     * @return int|WC_Order
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getProperOrder($orderContainer, $returnAs)
    {
        if (method_exists($orderContainer, 'get_id')) {
            $orderId = $orderContainer->get_id();
            $order = $orderContainer;
        } elseif ((int)$orderContainer > 0) {
            $order = new WC_Order($orderContainer);
        } elseif (is_object($orderContainer) && isset($orderContainer->id)) {
            $orderId = $orderContainer->id;
            $order = new WC_Order($orderId);
        } else {
            throw new Exception(
                sprintf('Order id not found when looked up in %s.', __FUNCTION__),
                400
            );
        }

        switch ($returnAs) {
            case 'order':
                $return = $order;
                break;
            default:
                $return = $orderId;
        }

        return $return;
    }

    /**
     * Render prefixed order note.
     *
     * @param $orderNote
     * @return string
     * @since 0.0.1.0
     */
    public static function getOrderNotePrefixed($orderNote)
    {
        return sprintf(
            '[%s] %s',
            WordPress::applyFilters('getOrderNotePrefix', Data::getPrefix()),
            $orderNote
        );
    }

    /**
     * @param $order
     * @param $pRequest
     * @since 0.0.1.0
     */
    private static function getHandledCallbackNullOrder($order, $pRequest)
    {
        if ($order === null) {
            Data::setLogError(
                sprintf(
                    __(
                        'Callback with parameter %s received, but failed because $order could not ' .
                        'instantiate and remained null.',
                        'trbwc'
                    ),
                    $pRequest
                )
            );
        }
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
            self::getCustomerRealAddress($order);
            self::setOrderStatusByCallback(
                Api::getResurs()->getOrderStatusByPayment($paymentId),
                $order
            );
        }
    }

    /**
     * @param $order
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getCustomerRealAddress($order)
    {
        $return = false;
        $resursPayment = Data::getOrderMeta('resurspayment', $order);
        if (is_object($resursPayment) && isset($resursPayment->customer)) {
            $billingAddress = $order->get_address('billing');
            $orderId = $order->get_id();
            if ($orderId > 0 && isset($resursPayment->customer->address)) {
                foreach (self::$getAddressTranslation as $item => $value) {
                    if (isset($billingAddress[$item], $resursPayment->customer->address->{$value}) &&
                        $billingAddress[$item] !== $resursPayment->customer->address->{$value}
                    ) {
                        update_post_meta(
                            $orderId,
                            sprintf('_billing_%s', $item),
                            $resursPayment->customer->address->{$value}
                        );
                        $return = true;
                    }
                }
            }
        }

        if ($return) {
            $synchNotice = __(
                'Resurs Bank billing address mismatch with current address in order. ' .
                'Data has synchronized with Resurs Bank billing data.',
                'resurs-bank-payment-gateway-for-woocommerce'
            );
            Data::setOrderMeta($order, 'customerSynchronization', strftime('%Y-%m-%d %H:%M:%S', time()));
            Data::setLogNotice($synchNotice);
            WooCommerce::setOrderNote(
                $order,
                $synchNotice
            );
        }

        return $return;
    }

    /**
     * @param $ecomOrderStatus
     * @param WC_Order $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function setOrderStatusByCallback($ecomOrderStatus, $order)
    {
        switch (true) {
            case $ecomOrderStatus & OrderStatus::PENDING:
                self::setOrderStatusWithNotice($order, OrderStatus::PENDING);
                break;
            case $ecomOrderStatus & OrderStatus::PROCESSING:
                self::setOrderStatusWithNotice($order, OrderStatus::PROCESSING);
                break;
            case $ecomOrderStatus & OrderStatus::AUTO_DEBITED:
                self::setOrderStatusWithNotice($order, OrderStatus::AUTO_DEBITED);
                break;
            case $ecomOrderStatus & OrderStatus::COMPLETED:
                self::setOrderStatusWithNotice($order, OrderStatus::COMPLETED);
                break;
            case $ecomOrderStatus & OrderStatus::ANNULLED:
                self::setOrderStatusWithNotice($order, OrderStatus::ANNULLED);
                break;
            case $ecomOrderStatus & OrderStatus::CREDITED:
                self::setOrderStatusWithNotice($order, OrderStatus::CREDITED);
                break;
            case $ecomOrderStatus & OrderStatus::MANUAL_INSPECTION:
                self::setOrderStatusWithNotice($order, OrderStatus::MANUAL_INSPECTION);
                break;
            default:
        }
    }

    /**
     * @param WC_Order $order
     * @param $ecomOrderStatus
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function setOrderStatusWithNotice($order, $ecomOrderStatus)
    {
        $currentStatus = $order->get_status();
        $requestedStatus = self::getOrderStatuses($ecomOrderStatus);

        if (strtolower($currentStatus) !== strtolower($requestedStatus)) {
            if ($ecomOrderStatus & OrderStatus::AUTO_DEBITED) {
                WooCommerce::setOrderNote(
                    $order,
                    __(
                        'Resurs Bank order status update indicates direct debited payment method.',
                        'trbwc'
                    )
                );
            }

            self::setOrderNote(
                $order,
                sprintf(
                    __('Order status update to %s has been queued.', 'trbwc'),
                    $requestedStatus
                )
            );

            $return = OrderStatusHandler::setOrderStatusWithNotice(
                $order,
                $requestedStatus,
                sprintf(
                    __('Resurs Bank queued order update: Change from %s to %s from queue.', 'trbwc'),
                    $currentStatus,
                    $requestedStatus
                )
            );
        } else {
            $orderStatusUpdateNotice = __(
                sprintf(
                    '%s notice: Request to set order to status "%s" but current status is already set.',
                    __FUNCTION__,
                    $requestedStatus
                ),
                'trbwc'
            );
            self::setOrderNote(
                $order,
                $orderStatusUpdateNotice
            );
            Data::setLogNotice($orderStatusUpdateNotice);
            // Tell them that this went almost well, if they ask.
            $return = true;
        }

        return $return;
    }

    /**
     * @param null $key
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getOrderStatuses($key = null)
    {
        $returnStatusString = 'on-hold';
        $autoFinalizationString = Data::getResursOption('order_instant_finalization_status');

        $return = WordPress::applyFilters('getOrderStatuses', [
            OrderStatus::PROCESSING => 'processing',
            OrderStatus::CREDITED => 'refunded',
            OrderStatus::COMPLETED => 'completed',
            OrderStatus::AUTO_DEBITED => $autoFinalizationString !== 'default' ? $autoFinalizationString : 'completed',
            OrderStatus::PENDING => 'on-hold',
            OrderStatus::ANNULLED => 'cancelled',
            OrderStatus::ERROR => 'on-hold',
            OrderStatus::MANUAL_INSPECTION => 'on-hold',
        ]);
        if (isset($key, $return[$key])) {
            $returnStatusString = $return[$key];
        }

        return $returnStatusString;
    }

    /**
     * @param $orderId
     * @throws ResursException
     * @since 0.0.1.0
     */
    private static function setSigningMarked($orderId, $byCallback)
    {
        if (Data::getOrderMeta('signingRedirectTime', $orderId) &&
            Data::getOrderMeta('bookPaymentStatus', $orderId) &&
            empty(Data::getOrderMeta('signingOk', $orderId))
        ) {
            Data::setOrderMeta($orderId, 'signingOk', strftime('%Y-%m-%d %H:%M:%S', time()));
            Data::setOrderMeta(
                $orderId,
                'signingConfirmed',
                sprintf(
                    'Callback:%s-%s',
                    $byCallback,
                    strftime('%Y-%m-%d %H:%M:%S', time())
                )
            );
        }
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
     * Apply actions to WooCommerce Action Queue.
     *
     * @param $queueName
     * @param $value
     * @since 0.0.1.0
     */
    public static function applyQueue($queueName, $value)
    {
        $applyArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                WordPress::getFilterName($queueName)
            ),
            $value,
        ];

        return WC()->queue()->add(
            ...array_merge($applyArray, WordPress::getFilterArgs(func_get_args()))
        );
        //WC()->queue()->schedule_recurring(time()+5, 2, WordPress::getFilterName($queueName));
    }

    /**
     * Set order status with prefixed note.
     *
     * @param $order
     * @param $newOrderStatus
     * @param $orderNote
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function setOrderStatusUpdate($order, $newOrderStatus, $orderNote)
    {
        return self::getProperOrder($order, 'order')->update_status(
            $newOrderStatus,
            self::getOrderNotePrefixed($orderNote)
        );
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is not in checkout.
     *
     * @since 0.0.1.0
     */
    public static function setAddToCart()
    {
        self::setCustomerCheckoutLocation(false);
    }

    /**
     * @since 0.0.1.0
     */
    public static function setUpdatedCart()
    {
        $isCheckout = is_checkout();

        try {
            if (WooCommerce::getValidCart()) {
                $currentTotal = WC()->cart->total;
                if ($currentTotal !== WooCommerce::getSessionValue('customerCartTotal')) {
                    $orderHandler = new OrderHandler();
                    $orderHandler->setCart(WC()->cart);
                    $orderHandler->setPreparedOrderLines();
                    self::setSessionValue('customerCartTotal', WC()->cart->total);
                    // Only update payment session if in RCO mode.
                    if (Data::getCheckoutType() === ResursDefault::TYPE_RCO &&
                        !empty(WooCommerce::getSessionValue('rco_order_id'))
                    ) {
                        Api::getResurs()->updateCheckoutOrderLines(
                            WooCommerce::getSessionValue('rco_order_id'),
                            $orderHandler->getOrderLines()
                        );
                    }
                }
            }
        } catch (Exception $e) {
            Data::setLogError(
                sprintf(
                    __('Exception (%s) from %s: %s.', 'trbwc'),
                    $e->getCode(),
                    __FUNCTION__,
                    $e->getMessage()
                )
            );
        }

        self::setCustomerCheckoutLocation($isCheckout);
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getValidCart($returnCart = false)
    {
        $return = false;

        if (isset(WC()->cart)) {
            $return = (WC()->cart->get_cart_contents_count() > 0);

            if (!empty(WC()->cart) && $return && $returnCart) {
                $return = WC()->cart->get_cart();
            }
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
        $fragments['rbwc_cart_total'] = (float)(WooCommerce::getValidCart() ? WC()->cart->total : 0.00);
        self::setCustomerCheckoutLocation(true);

        return $fragments;
    }

    /**
     * @param $arrayRequest
     * @since 0.0.1.0
     */
    public static function getOrderReviewSettings($arrayRequest)
    {
        // Rounding panic prevention.
        if (isset($_POST['payment_method']) && Data::isResursMethod($_POST['payment_method'])) {
            add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
        }
        self::setCustomerCheckoutLocation(true);
    }

    /**
     * @return array|mixed|string|null
     */
    public static function getCustomerCheckoutLocation()
    {
        return self::getSessionValue(self::$inCheckoutKey);
    }

    /**
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getAllowResursRun($return)
    {
        return $return;
    }
}