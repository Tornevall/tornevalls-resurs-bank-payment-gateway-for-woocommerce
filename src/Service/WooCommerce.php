<?php

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\PluginHooks;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings;
use Resursbank\Woocommerce\Util\Url;
use RuntimeException;
use stdClass;
use Throwable;
use WC_Order;
use WC_Product;
use function count;
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
    public static string $inCheckoutKey = 'customerWasInCheckout';

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
     * @param mixed $gateways
     * @return mixed
     * @throws ConfigException
     * @throws Throwable
     * @since 0.0.1.0
     */
    public static function getAvailableGateways(mixed $gateways): mixed
    {
        if (is_admin()) {
            return $gateways;
        }

        if (is_array($gateways)) {
            // Payment methods here are listed for non-admin-pages only. In admin, the only gateway visible
            // should be ResursDefault in its default state.
            try {
                $gateways += WooCommerce::getGatewaysFromPaymentMethods($gateways);
            } catch (Throwable $e) {
                // Catch errors if something goes wrong during gateway fetching.
                // If errors occurs in wp-admin, an error note will show up, instead of crashing the entire site.
                WordPress::setGenericError($e);
                Config::getLogger()->error($e);
            }
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
     * @param array $gateways
     * @return array
     * @since 0.0.1.0
     * @todo Create payment method cache-driver based on transients via ecom2 (WOO-847).
     */
    private static function getGatewaysFromPaymentMethods(array $gateways = []): array
    {
        try {
            $paymentMethodList = PaymentMethodRepository::getPaymentMethods(StoreId::getData());

            /** @var PaymentMethod $paymentMethod */
            foreach ($paymentMethodList as $paymentMethod) {
                $gateway = new ResursDefault(resursPaymentMethod: $paymentMethod);
                if ($gateway->is_available()) {
                    $gateways[RESURSBANK_MODULE_PREFIX . '_' . $paymentMethod->id] = $gateway;
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
     * Self-aware setup link. Used from filter, meaning this method looks unused.
     *
     * @param $links
     * @param $file
     * @param null $section
     * @return mixed
     * @noinspection PhpUnused
     */
    public static function getPluginAdminUrl($links, $file, $section = null): mixed
    {
        if (strpos($file, self::getBaseName()) !== false) {
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf(
                '<a href="%s?page=wc-settings&tab=%s&section=api_settings">%s</a>',
                admin_url('admin.php'),
                RESURSBANK_MODULE_PREFIX,
                'Settings'
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
        if (
            $order->meta_exists('resursBankPaymentFlow') &&
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
        $metaPrefix = RESURSBANK_MODULE_PREFIX;
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
        $metaPrefix = RESURSBANK_MODULE_PREFIX;
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
        if (($metaType === 'post') && preg_match(sprintf('/^%s/i', RESURSBANK_MODULE_PREFIX), $metaKey)) {
            $protected = true;
        }
        return $protected;
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
     * Set up a session based on how WooCommerce has it initiated. Value types are several.
     * @param string $key
     * @param array|string|stdClass $value
     * @since 0.0.1.0
     */
    public static function setSessionValue(string $key, array|string|stdClass $value): void
    {
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
        if (
            !empty($productSkuValue) &&
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
            WordPress::applyFilters('getOrderNotePrefix', RESURSBANK_MODULE_PREFIX),
            $orderNote
        );
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
     * @param $key
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getSessionValue($key): mixed
    {
        $return = null;
        $session = Url::getSanitizedArray(isset($_SESSION) ? $_SESSION : []);

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
}
