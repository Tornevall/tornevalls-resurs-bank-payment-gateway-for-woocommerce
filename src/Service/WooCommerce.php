<?php

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Cache\Filesystem;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecommerce\Types\OrderStatus;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursBank\Module\PluginHooks;
use ResursBank\Module\ResursBankAPI;
use ResursBank\Service\OrderStatus as OrderStatusHandler;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings;
use ResursException;
use RuntimeException;
use stdClass;
use WC_Order;
use WC_Product;
use WC_Tax;
use function count;
use function func_get_args;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

/**
 * Class WooCommerce related actions.
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
    private static $requiredVersion = '3.5.0';

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getActiveState(): bool
    {
        // Initialize plugin functions.
        new PluginHooks();

        add_filter('rbwc_is_available', 'ResursBank\Service\WooCommerce::rbwcIsAvailable', 999);

        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins')),
            true
        );
    }

    /**
     * @return bool
     * @since 0.0.1.8
     */
    public static function rbwcIsAvailable(): bool
    {
        return true;
    }

    /**
     * @param $settings
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getSettingsPages($settings)
    {
        if (is_admin()) {
            $settings[] = new Settings();
        }

        return $settings;
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.6
     */
    public static function hasDualCustomerTypes(): bool
    {
        return self::hasMethodsNatural() && self::hasMethodsLegal();
    }

    /**
     * Returns true if private/natural methods is present.
     *
     * @return bool
     * @throws Exception
     * @since 0.0.1.6
     */
    public static function hasMethodsNatural(): bool
    {
        return (bool)self::getMethodsByType('natural');
    }

    /**
     * @param $type
     * @param string $returnAs
     * @return array|bool
     * @throws Exception
     * @since 0.0.1.6
     */
    private static function getMethodsByType($type, $returnAs = 'bool')
    {
        try {
            $paymentMethods = ResursBankAPI::getPaymentMethods(true);
        } catch (Exception $e) {
            if ($e->getCode() === Data::UNSET_CREDENTIALS_EXCEPTION) {
                $paymentMethods = [];
            } else {
                throw $e;
            }
        }
        $returnBool = false;

        $paymentMethodList = [];
        if (is_array($paymentMethods) && count($paymentMethods)) {
            foreach ($paymentMethods as $method) {
                switch ($type) {
                    case 'legal' && $method->enabledForLegalCustomer:
                    case 'natural' && $method->enabledForNaturalCustomer:
                        $paymentMethodList[] = $method;
                        break;
                    default:
                }
            }
            $returnBool = count($paymentMethodList) > 0;
        }

        return $returnAs !== 'bool' ? $paymentMethodList : $returnBool;
    }

    /**
     * Returns true if company/legal methods is present.
     *
     * @throws Exception
     * @since 0.0.1.6
     */
    public static function hasMethodsLegal(): bool
    {
        return (bool)self::getMethodsByType('legal');
    }

    /**
     * @param mixed $gateways
     * @return mixed
     * @throws ConfigException
     * @since 0.0.1.0
     */
    public static function getAvailableGateways(mixed $gateways): mixed
    {
        if (is_admin()) {
            return $gateways;
        }

        // Payment methods here are listed for non-admin-pages only. In admin, the only gateway visible
        // should be ResursDefault in its default state.

        $existingPaymentMethodGateways = WooCommerce::getGatewaysFromPaymentMethods($gateways);
        $customerCountry = Data::getCustomerCountry();

        // When customer country is different from store country, we want to remove all payment methods
        // except the credit cards that can operate outside borders.
        if ($customerCountry !== get_option('woocommerce_default_country')) {
            foreach ($existingPaymentMethodGateways as $gateway) {
                if ($gateway instanceof ResursDefault && $gateway->isAvailableOutsideBorders()) {
                    $gateways[] = $gateway;
                }
            }
        } else {
            $gateways += $existingPaymentMethodGateways;
        }

        return $gateways;
    }

    /**
     * @param mixed $gateways
     * @return mixed
     * @see https://rudrastyh.com/woocommerce/get-and-hook-payment-gateways.html
     */
    public static function getGateways(mixed $gateways): mixed
    {
        if (is_array($gateways)) {
            $gateways[] = ResursDefault::class;
        }

        return $gateways;
    }

    /**
     * Handle payment methods as separate gateways without the necessary steps to have separate classes on disk
     * or written in database.
     *
     * This method fetches payment methods live from Resurs Bank and generates a gateway for each of them
     * by the ResursDefault-class. In case there are a file cache available, the methods will be stored temporarily
     * there, instead, for the performance. We could however use transients for local storage too, if there is no
     * file cache available in ecom2.
     *
     * @param array $gateways
     * @return array
     * @throws ConfigException
     * @since 0.0.1.0
     */
    private static function getGatewaysFromPaymentMethods(array $gateways = []): array
    {
        // Local fail-over if ecom2 has no initial file cache available.
        $canUseTransientCache = Config::getCache() instanceof None;
        $transientMethodList = null;

        // We want to fetch payment methods from storage at this point, in cae Resurs Bank API is down.
        try {
            if ($canUseTransientCache) {
                // @todo Centralize Settings::Prefix to use with the key payment_methods as WC_Settings_Page is not
                // @todo available from checkout pages..
                $transientMethodList = get_transient(transient: 'resursbank_payment_methods');
            }

            $paymentMethodList = !$transientMethodList instanceof PaymentMethodCollection ?
                PaymentMethodRepository::getPaymentMethods(StoreId::getData()) : $transientMethodList;

            // Save short term cache in case of problems that may disable sections of WooCommerce that is supposed
            // to be alive during network errors. In case of changes Resurs side, the transient ttl should be as
            // short as possible. However, this only applies to cases when no local ecom-cache is available.
            if ($canUseTransientCache) {
                set_transient(
                    transient: 'resursbank_payment_methods',
                    value: $paymentMethodList,
                    expiration: 600
                );
            }

            foreach ($paymentMethodList as $paymentMethod) {
                $gateway = new ResursDefault(resursPaymentMethod: $paymentMethod);
                if ($gateway->is_available()) {
                    $gateways[] = $gateway;
                }
            }
        } catch (Exception $e) {
            // If we run the above request live, when the APIs are down, we want to catch the exception silently
            // or the site will break. If we are located in admin, we also want to visualize the exception as
            // a message not a crash.
            WordPress::setGenericError($e);
            Data::writeLogException($e, __FUNCTION__);
        }

        // If request failed or something caused an empty result, we should still return the list of gateways as
        // gateways. Have in mind that this array may already have content from other plugins.
        return $gateways;
    }

    /**
     * @param bool $returnCart
     * @return bool|array
     * @since 0.0.1.0
     */
    public static function getValidCart(bool $returnCart = false): bool|array
    {
        $return = false;

        if (isset(WC()->cart)) {
            $cartContentCount = WC()->cart->get_cart_contents_count();
            $return = $cartContentCount > 0;

            if ($returnCart && $return && !empty(WC()->cart)) {
                $return = WC()->cart->get_cart();
            }
        }

        return $return;
    }

    /**
     * Self-aware setup link.
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
                admin_url('admin.php'),
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
    public static function getBaseName(): string
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
    public static function getRequiredVersion(): string
    {
        return self::$requiredVersion;
    }

    /**
     * @param mixed $order
     * @throws Exception
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getAdminAfterOrderDetails($order = null)
    {
        // Considering this place as a safe place to apply display in styles.
        Data::getSafeStyle();

        if ($order instanceof WC_Order) {
            $paymentMethod = $order->get_payment_method();
            if (!Data::canHandleOrder($paymentMethod)) {
                self::getAdminAfterOldCheck($order);
            }
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
            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate(
                    'adminpage_woocommerce_version22',
                    [
                        'wooPlug22VersionInfo' => __(
                            'Order has not been created by this plugin and the original plugin is currently unavailable.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ]
                )
            );
        }
    }

    /**
     * @param array $ecomHolder
     * @param array $metaArray
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getMetaDataFromOrder(array $ecomHolder, array $metaArray)
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
                if (is_string($metaValue) || is_array($metaValue)) {
                    $ecomMetaArray[$metaKey] = $metaValue;
                }
            }
        }

        return array_merge((array)self::getPurgedMetaData($ecomHolder), (array)self::getPurgedMetaData($ecomMetaArray));
    }

    /**
     * @param $metaDataContainer
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getPurgedMetaData($metaDataContainer): array
    {
        $purgeArray = WordPress::applyFilters('purgeMetaData', [
            'orderSigningPayload',
            'orderapi',
            'apiDataId',
            'cached',
            'requestMethod',
        ]);
        // Not necessary for customer to view.
        $metaPrefix = Data::getPrefix();
        if (is_array($metaDataContainer) && count($metaDataContainer)) {
            foreach ($purgeArray as $purgeKey) {
                if (isset($metaDataContainer[$purgeKey])) {
                    unset($metaDataContainer[$purgeKey]);
                }
                $prefixed = sprintf('%s_%s', $metaPrefix, $purgeKey);
                if (isset($metaDataContainer[$prefixed])) {
                    unset($metaDataContainer[$prefixed]);
                }
            }
        }
        return (array)$metaDataContainer;
    }

    /**
     * @param $methodName
     * @return bool
     * @since 0.0.1.0
     */
    public static function getIsOldMethod($methodName): bool
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
     * Address blocks that on demand can be displayed in the upper section of the order view.
     * @param null $order
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminAfterBilling($order = null)
    {
        Data::getSafeStyle();
        if (!empty($order) &&
            WordPress::applyFilters('canDisplayOrderInfoAfterBilling', false) &&
            Data::canHandleOrder($order->get_payment_method())
        ) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate('adminpage_billing.phtml', $orderData)
            );
        }
    }

    /**
     * Address blocks that on demand can be displayed in the upper section of the order view.
     * @param null $order
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminAfterShipping($order = null)
    {
        Data::getSafeStyle();
        if (!empty($order) &&
            WordPress::applyFilters('canDisplayOrderInfoAfterShipping', false) &&
            Data::canHandleOrder($order->get_payment_method())
        ) {
            $orderData = Data::getOrderInfo($order);
            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate('adminpage_shipping.phtml', $orderData)
            );
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
        if (isset($return['ecom']) && is_array($return['ecom']) && count($return['ecom'])) {
            $return['customer_billing'] = isset($return['ecom']->customer->address) ? $return['ecom']->customer->address : [];
            $return['customer_shipping'] = isset($return['ecom']->deliveryAddress) ? $return['ecom']->deliveryAddress : [];
        }

        return $return;
    }

    /**
     * @param $return
     * @param $scriptName
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnused
     */
    public static function getGenericLocalization($return, $scriptName)
    {
        // @todo ECom1 was generating a temporary order id sequence that do longer exist. This has to be fixed
        // @todo some other way then this random sha1-string.
        if (preg_match('/_checkout$/', $scriptName) && is_checkout() && Data::hasCredentials()) {
            $return[sprintf(
                '%s_rco_suggest_id',
                Data::getPrefix()
            )] = sha1(microtime(true));
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
            'jwt_client_id',
            'isCurrentCredentials',
            'environment',
            'cache',
        ];

        if (isset($return['ecom'])) {
            $purgedEcom = (array)$return['ecom'];
            $billingAddress = $purgedEcom['customer']->address ?? [];
            $deliveryAddress = $purgedEcom['deliveryAddress'] ?? [];

            foreach ($purgedEcom as $key => $value) {
                if (in_array($key, $purge, true)) {
                    unset($purgedEcom[$key]);
                }
            }
            $return['ecom_short'] = $purgedEcom;
            $return['ecom_short']['billingAddress'] = implode("\n", self::getCompactAddress($billingAddress));
            $return['ecom_short']['deliveryAddress'] = implode("\n", self::getCompactAddress($deliveryAddress));
        }

        return $return;
    }

    /**
     * @param $addressData
     * @return array
     * @since 0.0.1.7
     */
    private static function getCompactAddress($addressData): array
    {
        // We no longer have to make this data compact, and show the full customer object.
        // The new way of showing the data in the meta boxes makes this much better.
        $purge = [
            'fullName',
        ];
        $ignore = ['country', 'postalCode', 'postalArea'];

        $return = [
            'fullName' => sprintf(
                '%s %s',
                $addressData->firstName ?? '',
                $addressData->lastName ?? ''
            ),
        ];
        foreach ($purge as $key) {
            if (isset($addressData->{$key})) {
                unset($addressData->{$key});
            }
        }
        foreach ($addressData as $key => $value) {
            if (!in_array($key, $ignore)) {
                $return[$key] = $value;
            }
        }

        $return['postalCity'] = sprintf(
            '%s-%s %s',
            $addressData->country ?? '',
            $addressData->postalCode ?? '',
            $addressData->postalArea ?? ''
        );

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
        Data::writeLogEvent(
            Data::CAN_LOG_JUNK,
            sprintf(
                __(
                    'Session value %s set to %s.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                self::$inCheckoutKey,
                $customerIsInCheckout ? 'true' : 'false'
            )
        );
        self::setSessionValue(self::$inCheckoutKey, $customerIsInCheckout);
    }

    /**
     * @param string $key
     * @param string|array|stdClass $value
     * @since 0.0.1.0
     */
    public static function setSessionValue($key, $value)
    {
        Data::writeLogEvent(
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
    private static function getSession(): bool
    {
        global $woocommerce;

        $return = false;
        if (isset($woocommerce->session) && !empty($woocommerce->session)) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param WC_Product $product
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getProperArticleNumber($product)
    {
        $return = $product->get_id();
        $productSkuValue = $product->get_sku();
        if (!empty($productSkuValue) &&
            WordPress::applyFilters('preferArticleNumberSku', Data::getResursOption('product_sku'))
        ) {
            $return = $productSkuValue;
        }

        return WordPress::applyFilters('getArticleNumber', $return, $product);
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getWcApiUrl(): string
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
        Data::writeLogNotice($logNotice);
        $callbackType = Data::getRequest('c');
        $replyArray = [
            'aliveConfirm' => true,
            'i' => '',
            'actual' => $callbackType,
            'errors' => [
                'code' => 0,
                'message' => '',
            ],
        ];

        // If there is a payment, there must be a digest.
        $pRequest = Data::getRequest('p');
        if (!empty($pRequest)) {
            $orderId = Data::getOrderByEcomRef(Data::getRequest('p'));

            $replyArray['i'] = $orderId;
            if ($orderId) {
                $order = new WC_Order($orderId);
                Data::setOrderMeta($order, 'resursCallbackReceived', true);
                self::setOrderNote(
                    new WC_Order($orderId),
                    $logNotice
                );
            }

            self::getHandledCallbackNullOrder($order, $pRequest);

            $cbUri = $_SERVER['REQUEST_URI'] ?: '';
            if (!empty($cbUri)) {
                Data::writeLogByLogLevel(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf('Callback received by URI: %s', $cbUri)
                );
            }

            $callbackEarlyFailure = false;
            try {
                self::applyMock('updateCallbackException');
                if ($order instanceof WC_Order) {
                    Data::setOrderMeta(
                        $order,
                        sprintf('callback_%s_receive', $callbackType),
                        date('Y-m-d H:i:s', time()),
                        true,
                        true
                    );
                } else {
                    throw new RuntimeException(
                        'Failed to instantiate $order during callback handling. Callback not updated.',
                        OrderStatusHandler::HTTP_RESPONSE_GONE_NOT_OURS
                    );
                }
            } catch (Exception $e) {
                $callbackEarlyFailure = true;
                $replyArray['aliveConfirm'] = false;
                $code = $e->getCode();
                $replyArray['errors'] = [
                    'code' => $code,
                    'message' => $e->getMessage(),
                ];
                Data::writeLogException($e, __FUNCTION__);
            }

            if (!$callbackEarlyFailure) {
                // Digest vs saltkey checking. Reaching this place means both digest and order is proper.
                if ($getConfirmedSalt && $orderId) {
                    /** @noinspection BadExceptionsProcessingInspection */
                    try {
                        self::getUpdatedOrderByCallback(Data::getRequest('p'), $orderId, $order);
                        self::setSigningMarked($orderId, $callbackType);
                        WordPress::doAction(
                            sprintf('callback_received_%s', $callbackType),
                            $order,
                            $order
                        );
                        $code = OrderStatusHandler::HTTP_RESPONSE_OK;
                        $responseString = 'OK';
                    } catch (Exception $e) {
                        $code = $e->getCode();
                        $responseString = $e->getMessage();
                        Data::writeLogException($e, __FUNCTION__);
                    }
                } else {
                    // Reaching here means something went wrong.
                    $code = OrderStatusHandler::HTTP_RESPONSE_DIGEST_IS_WRONG; // Not acceptable
                    $responseString = 'Digest rejected.';
                    // If order id is missing, we know that the callback is plausibly sent for another store.
                    // Are we switching between production and test on the same site? This might be the cause.
                    if (!$orderId) {
                        $code = OrderStatusHandler::HTTP_RESPONSE_GONE_NOT_OURS;
                        $responseString = 'Order is not ours or can not be found.';
                        // Only allow other responses if the order does not exist in the system.
                        // If there is a proper order, but with a miscalculated digest, callbacks should
                        // still be rejected with the bad digest message.
                        if ((bool)Data::getResursOption('accept_rejected_callbacks')) {
                            // So if we accept rejects, we will tell Resurs callbacks that callback was ok
                            // anyway. Which makes them stop sending further.
                            $code = OrderStatusHandler::HTTP_RESPONSE_NOT_OURS_BUT_ACCEPTED;
                            $responseString = 'Order is not ours, but it is still accepted.';
                        }
                    }
                    Data::writeLogEvent(
                        Data::CAN_LOG_ORDER_EVENTS,
                        sprintf(
                            __(
                                'Callback received for order "%s" but something went wrong: %s',
                                'resurs-bank-payments-for-woocommerce'
                            ),
                            $orderId,
                            $responseString
                        )
                    );
                    // If order id existed, we'll keep using the digestive error.
                    $replyArray['errors'] = [
                        'code' => $code,
                        'message' => $responseString,
                    ];
                }
            }
        }

        if ($callbackType === 'TEST') {
            $responseString = 'Test OK';
            Data::setResursOption('resurs_callback_test_response', time());
            Data::setResursOption('resurs_callback_test_start', null);
            $code = OrderStatusHandler::HTTP_RESPONSE_TEST_OK;

            Data::writeLogNotice('Callback TEST OK.');

            // There are not digest codes available in this state so we should throw the callback handler
            // a success regardless.
            $replyArray['digestCode'] = OrderStatusHandler::HTTP_RESPONSE_TEST_OK;
        }

        Data::writeLogNotice(
            sprintf(
                __(
                    'Callback %s for %s received.',
                    'resurs-bank-payments-for-woocommerce'
                ),
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
    private static function getConfirmedSalt(): bool
    {
        return ResursBankAPI::getResurs()->getValidatedCallbackDigest(
            Data::getRequest('p'),
            self::getCurrentSalt(),
            Data::getRequest('d'),
            Data::getRequest('r')
        );
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private static function getCurrentSalt(): string
    {
        return (string)Data::getResursOption('salt');
    }

    /**
     * @param $getConfirmedSalt
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getCallbackLogNotice($getConfirmedSalt): string
    {
        return sprintf(
            __(
                'Callback received from Resurs Bank (%s): %s (Digest Status: %s, External ID: %s, Internal ID: %d).',
                'resurs-bank-payments-for-woocommerce'
            ),
            $_SERVER['REMOTE_ADDR'] ?? 'NO IP FOUND',
            Data::getRequest('c'),
            $getConfirmedSalt ? __(
                'Valid',
                'resurs-bank-payments-for-woocommerce'
            ) : __(
                'Invalid',
                'resurs-bank-payments-for-woocommerce'
            ),
            Data::getRequest('p'),
            Data::getOrderByEcomRef(Data::getRequest('p'))
        );
    }

    /**
     * Set order note, but prefixed by plugin name.
     *
     * @param $order
     * @param $orderNote
     * @param int $is_customer_note
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function setOrderNote($order, $orderNote, $is_customer_note = 0): bool
    {
        $return = false;

        $properOrder = self::getProperOrder($order, 'order');
        if (method_exists($properOrder, 'get_id') && $properOrder->get_id()) {
            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __(
                        'setOrderNote for %s: %s'
                    ),
                    $properOrder->get_id(),
                    $orderNote
                )
            );

            $return = $properOrder->add_order_note(
                self::getOrderNotePrefixed($orderNote),
                $is_customer_note
            );
        }

        return (bool)$return;
    }

    /**
     * Centralized order retrieval.
     * @param $orderContainer
     * @param $returnAs
     * @param bool $log
     * @return int|WC_Order
     * @since 0.0.1.0
     */
    public static function getProperOrder($orderContainer, $returnAs, $log = false)
    {
        if (is_object($orderContainer) && method_exists($orderContainer, 'get_id')) {
            $orderId = $orderContainer->get_id();
            $order = $orderContainer;
        } elseif ((int)$orderContainer > 0) {
            $order = new WC_Order($orderContainer);
            $orderId = $orderContainer;
        } elseif (is_object($orderContainer) && isset($orderContainer->id)) {
            $orderId = $orderContainer->id;
            $order = new WC_Order($orderId);
        } else {
            throw new RuntimeException(
                sprintf('Order id not found when looked up in %s.', __FUNCTION__),
                400
            );
        }

        if ($log) {
            Data::writeLogNotice(
                sprintf(
                    __(
                        'getProperOrder for %s (as %s).',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    $orderId,
                    $returnAs
                )
            );
        }

        return $returnAs === 'order' ? $order : $orderId;
    }

    /**
     * Render prefixed order note.
     *
     * @param $orderNote
     * @return string
     * @since 0.0.1.0
     */
    public static function getOrderNotePrefixed($orderNote): string
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
            Data::writeLogError(
                sprintf(
                    __(
                        'Callback with parameter %s received, but failed because $order could not ' .
                        'instantiate and remained null.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    $pRequest
                )
            );
        }
    }

    /**
     * Create a mocked moment if test and allowed mocking is enabled.
     * @param $mock
     * @return mixed|void
     * @since 0.0.1.0
     */
    public static function applyMock($mock)
    {
        if (Data::canMock($mock)) {
            return WordPress::applyFilters(
                sprintf('mock%s', ucfirst($mock)),
                null
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
    private static function getUpdatedOrderByCallback($paymentId, $orderId, $order): void
    {
        if ($orderId) {
            $orderHandler = new OrderHandler();
            $orderHandler->getCustomerRealAddress($order);

            self::setOrderStatus($order);
        }
    }

    /**
     * Set order status together with a notice.
     * The returned values in the method usually was set to return a boolean for success, but should no longer
     * be depending on this. Other functions using this feature should not be required to validate success.
     *
     * @param WC_Order $order
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function setOrderStatus($order)
    {
        // Don't get fooled by the same name. This function is set elsewhere.
        return OrderStatusHandler::setOrderStatusByQueue($order);
    }

    /**
     * @param null $key
     * @return mixed
     * @since 0.0.1.0
     * @noinspection PhpDeprecationInspection
     */
    public static function getOrderStatuses($key = null)
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
        } elseif ($key & OrderStatus::AUTO_DEBITED) {
            $returnStatusString = $return[OrderStatus::AUTO_DEBITED];
        }

        return $returnStatusString;
    }

    /**
     * @param $orderId
     * @throws Exception
     * @throws ResursException
     * @since 0.0.1.0
     */
    private static function setSigningMarked($orderId, $byCallback): void
    {
        if (Data::getOrderMeta('signingRedirectTime', $orderId) &&
            Data::getOrderMeta('bookPaymentStatus', $orderId) &&
            empty(Data::getOrderMeta('signingOk', $orderId))
        ) {
            Data::writeLogNotice(sprintf('%s for %s -- OK', __FUNCTION__, $orderId));
            Data::setOrderMeta($orderId, 'signingOk', date('Y-m-d H:i:s', time()));
            Data::setOrderMeta(
                $orderId,
                'signingConfirmed',
                sprintf(
                    'Callback:%s-%s',
                    $byCallback,
                    date('Y-m-d H:i:s', time())
                )
            );

            return;
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
        $sProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $replyString = sprintf('%s %d %s', $sProtocol, $code, $httpString);
        header('Content-type: application/json');
        header($replyString, true, $code);
        // Can not sanitize output as the browser is strictly typed to specific content.
        echo json_encode($out);
        exit;
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
    public static function setOrderStatusUpdate($order, $newOrderStatus, $orderNote): bool
    {
        return OrderStatusHandler::setOrderStatusByQueue($order);
    }

    /**
     * Apply actions to WooCommerce Action Queue.
     *
     * @param $queueName
     * @param $value
     * @return string
     * @since 0.0.1.0
     */
    public static function applyQueue($queueName, $value): string
    {
        $applyArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                WordPress::getFilterName($queueName)
            ),
            $value,
        ];

        // Special debugging row for queued order statuses.
        //(new PluginHooks())->updateOrderStatusByQueue($value[0]);

        return WC()->queue()->add(
            ...array_merge($applyArray, WordPress::getFilterArgs(func_get_args()))
        );
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A watchguard for that moment, when customer is not in checkout.
     *
     * @since 0.0.1.0
     */
    public static function setAddToCart(): void
    {
        self::setCustomerCheckoutLocation(customerIsInCheckout: false);
    }

    /**
     * @since 0.0.1.0
     */
    public static function setUpdatedCart()
    {
        $isCheckout = is_checkout();

        try {
            // No need to update cart if not in checkout (and in admin).
            if ($isCheckout && !is_admin() && self::getValidCart()) {
                $currentTotal = WC()->cart->total;
                self::setSessionValue('customerCartTotal', WC()->cart->total);
                if ((float)$currentTotal) {
                    $orderHandler = new OrderHandler();
                    $orderHandler->setCart(WC()->cart);
                    $orderHandler->setPreparedOrderLines();
                    // Only update payment session if in RCO mode.
                    if (Data::getCheckoutType() === ResursDefault::TYPE_RCO &&
                        !empty(self::getSessionValue('rco_order_id')) &&
                        (float)$currentTotal > 0.00
                    ) {
                        try {
                            ResursBankAPI::getResurs()->updateCheckoutOrderLines(
                                self::getSessionValue('rco_order_id'),
                                $orderHandler->getOrderLines()
                            );
                        } catch (Exception $e) {
                            Data::writeLogError(
                                sprintf(
                                    __(
                                        'Exception (%s) from %s: %s.',
                                        'resurs-bank-payments-for-woocommerce'
                                    ),
                                    $e->getCode(),
                                    __FUNCTION__,
                                    $e->getMessage()
                                )
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Data::writeLogError(
                sprintf(
                    __('Exception (%s) from %s: %s.', 'resurs-bank-payments-for-woocommerce'),
                    $e->getCode(),
                    __FUNCTION__,
                    $e->getMessage()
                )
            );
        }

        self::setCustomerCheckoutLocation($isCheckout);
    }

    /**
     * @param $key
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getSessionValue($key): mixed
    {
        $return = null;
        $session = Data::getSanitizedArray(isset($_SESSION) ? $_SESSION : []);

        if (self::getSession()) {
            $return = WC()->session->get($key);
        } elseif (isset($_SESSION[$key])) {
            $return = $session[$key] ?? '';
        }

        return $return;
    }

    /**
     * Since ecom2 does not want trailing slashes in its logger, we use this method to trim away
     * all trailing slashes.
     * @return string
     */
    public static function getPluginLogDir(): string
    {
        $pluginLogDir = preg_replace('/\/$/', '', Data::getResursOption('log_dir'));

        return is_dir($pluginLogDir) ? $pluginLogDir : '';
    }

    /**
     * v3core: Checkout vs Cart Manipulation - A moment when customer is in checkout.
     *
     * @param $fragments
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getReviewFragments($fragments): array
    {
        $fragments['#rbGetAddressFields'] = FormFields::getGetAddressForm(null, true);
        $fragments['rbwc_cart_total'] = (float)(self::getValidCart() ? WC()->cart->total : 0.00);
        $fragments['fragmethod'] = Data::getMethodFromFragmentOrSession();
        self::setCustomerCheckoutLocation(true);

        return $fragments;
    }

    /**
     * @since 0.0.1.0
     * @noinspection PhpUnusedParameterInspection
     */
    public static function getOrderReviewSettings()
    {
        // Rounding panic prevention.
        if (Data::isResursMethod(Data::getRequest('payment_method', $_POST))) {
            $methodFromPost = Data::getRequest('payment_method', $_POST);
            if (!empty($methodFromPost)) {
                WooCommerce::setSessionValue(
                    'paymentMethod',
                    Data::getResursMethodFromPrefix($methodFromPost)
                );
            }
            add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
        }
        self::setCustomerCheckoutLocation(true);
    }

    /**
     * @throws Exception
     * @since 0.0.1.5
     */
    public static function applyVisualPartPaymentReview()
    {
        if (Data::getResursOption('part_payment_sums') && self::getValidCart()) {
            echo sprintf(
                '<tr class="order-total">
                    <td colspan="2">%s</td>
                </tr>',
                Data::getAnnuityFactors(WC()->cart->total, false)
            );
        }
    }

    /**
     * @throws Exception
     * @since 0.0.1.5
     */
    public static function applyVisualPartPaymentCartTotals()
    {
        if (Data::getResursOption('part_payment_sums') && self::getValidCart()) {
            echo sprintf(
                '<tr class="order-total">
                    <td colspan="2">%s</td>
                </tr>',
                Data::getAnnuityFactors(WC()->cart->total, false)
            );
        }
    }

    /**
     * Apply fee naturally to cart.
     *
     * @throws Exception
     * @since 0.0.1.5
     */
    public static function applyVisualPaymentFee()
    {
        $paymentMethodbySession = Data::getPaymentMethodBySession();
        $customFee = self::getCustomFee($paymentMethodbySession);
        if ($paymentMethodbySession && $customFee > 0) {
            WC()->cart->add_fee(
                WordPress::applyFilters(
                    'getFeeDescription',
                    __('Payment fee', 'resurs-bank-payments-for-woocommerce')
                ),
                $customFee,
                true,
                WooCommerce::getCustomTaxRate()
            );
        }
    }

    /**
     * @param $paymentMethod
     * @return string
     * @since 0.0.1.5
     */
    public static function getCustomFee($paymentMethod): string
    {
        return Data::getResursOption(sprintf('method_custom_fee_%s', $paymentMethod));
    }

    /**
     * Get tax rate from plugin setting when nothing else applies.
     * @return int
     * @since 0.0.1.5
     */
    public static function getCustomTaxRate(): int
    {
        $return = 0;
        $rateClassName = Data::getResursOption('internal_tax');
        $rateByClass = WC_Tax::get_rates($rateClassName === 'standard' ? '' : $rateClassName);

        if (is_array($rateByClass) && count($rateByClass)) {
            $setRate = array_pop($rateByClass);
            $return = $setRate['rate'] ?? 0;
        }

        return $return;
    }

    /**
     * @param $paymentMethod
     * @return string
     * @since 0.0.1.5
     */
    public static function getCustomDescription($paymentMethod): string
    {
        return Data::getResursOption(sprintf('method_custom_description_%s', $paymentMethod));
    }

    /**
     * @return array|mixed|string|null
     * @throws Exception
     */
    public static function getCustomerCheckoutLocation()
    {
        return self::getSessionValue(self::$inCheckoutKey);
    }

    /**
     * @param $orderData
     * @since 0.0.1.0
     */
    public static function getEcomHadProblemsInfo($orderData)
    {
        $return = false;

        if (isset($orderData['ecom_had_reference_problems']) && $orderData['ecom_had_reference_problems']) {
            $return = sprintf(
                __(
                    'This payment is marked with reference problems. This means that there might have been ' .
                    'problems when the payment was executed and tried to update the payment reference (%s) to a new ' .
                    'id (%s). You can check the UpdatePaymentReference values for errors.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $orderData['resurs_secondary'] ??
                __(
                    '[missing reference]',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $orderData['resurs']
            );
        }

        return $return;
    }
}
