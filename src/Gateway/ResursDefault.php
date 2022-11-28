<?php

// We do use camel cases in this file.

/** @noinspection PhpCSValidationInspection */
/** @noinspection EfferentObjectCouplingInspection */
/** @noinspection PhpAssignmentInConditionInspection */

namespace ResursBank\Gateway;

use Error;
use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Http\Controller;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Customer;
use Resursbank\Ecom\Module\Customer\Repository as CustomerRepository;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Ecom\Lib\Utilities\Strings;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Application;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use ResursBank\Module\Callback as CallbackModule;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursBank\Module\ResursBankAPI;
use ResursBank\Service\OrderHandler;
use ResursBank\Service\OrderStatus;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Url;
use RuntimeException;
use stdClass;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;
use WC_Product;
use WC_Tax;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_object;
use function sha1;
use function uniqid;

/**
 * Default payment gateway class. Written to handle payment methods dynamically but still be able to show
 * a static configuration in the admin panel. The configuration view is separated from the "payments" with
 * options slimmed to a minor view. See todo below about merging this class into the payments view again.
 *
 * Class primarily handles payments, orders and callbacks dynamically, with focus on less loss
 * of data during API communication by converting API-calls to base64-strings which prevents charset problems.
 *
 * @package Resursbank\Gateway
 * @since 0.0.1.0
 */
class ResursDefault extends WC_Payment_Gateway
{
    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_FINALIZED = 'completed';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_BOOKED = 'processing';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_FROZEN = 'on-hold';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_SIGNING = 'on-hold';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_DENIED = 'failed';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_FAILED = 'failed';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * This prefix is used for various parts of the settings by WooCommerce,
     * for example, as an ID for these settings, and as a prefix for the values
     * in the database.
     */
    public const PREFIX = 'resursbank';

    /**
     * @var WC_Order $order
     * @since 0.0.1.0
     */
    protected WC_Order $order;

    /**
     * Main API. Use as primary communicator. Acts like a bridge between the real API.
     * @var ResursBankAPI $API
     * @since 0.0.1.0
     */
    protected $API;
    /**
     * WooCommerce cart. On WooCommerce-side, this is nullable, so it should only be set if available.
     * @var WC_Cart $cart
     * @since 0.0.1.0
     */
    protected WC_Cart $cart;
    /**
     * @var array $applicantPostData Applicant request.
     * @since 0.0.1.0
     */
    private array $applicantPostData = [];
    /**
     * Responses from the SOAP-API, so that different segments of this class can utilize it.
     * @var stdClass $paymentResponse
     * @since 0.0.1.0
     * @todo We don't use SOAP anymore.
     */
    private stdClass $paymentResponse;
    /**
     * Complete centralized order data extracted from both metas, WC order information and Resurs (getPayment).
     * @var array
     * @since 0.0.1.0
     */
    private array $wcOrderData;
    /**
     * Data that will be sent between Resurs Bank and ourselves. This array will be merged into base64-encoded strings
     * to maintain the charset integrity.
     *
     * @var array $apiData
     * @since 0.0.1.0
     */
    private array $apiData = [];
    /**
     * ID for the apiData array, stored as metadata so that we can refetch it properly in case of losses.
     * @var string $apiDataId
     * @since 0.0.1.0
     */
    private string $apiDataId = '';
    /**
     * This instance payment method from Resurs Bank.
     * @var PaymentMethod $paymentMethodInformation
     */
    private PaymentMethod $paymentMethodInformation;

    /**
     * ResursDefault constructor.
     *
     * @param PaymentMethod|null $resursPaymentMethod Making sure the gateway is reachable even if initialization has failed.
     * @throws Exception
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @since 0.0.1.0
     */
    public function __construct(
        public readonly ?PaymentMethod $resursPaymentMethod = null
    ) {
        $this->initializePaymentMethod(paymentMethod: $resursPaymentMethod);
    }

    /**
     * Initialize the gateway with the current Resurs payment method model.
     * Things initialized here is mostly defaults, since we are depending on features that can work independently
     * without spontaneous crashes.
     *
     * @param PaymentMethod|null $paymentMethod
     * @return void
     * @throws Exception
     */
    private function initializePaymentMethod(?PaymentMethod $paymentMethod = null): void
    {
        // Required: Making sure that woocommerce content is available. Like the cart.
        global $woocommerce;

        // Validate a cart if present and put it in the class, so that it can be used for the payment
        // later on. If there is no customer cart, it exists as a nullable in $woocommerce.
        if ($woocommerce->cart instanceof WC_Cart) {
            $this->cart = $woocommerce->cart;
        }

        // Below is initial default preparations for the gateway, which is normally used to show up in wp-admin
        // When this class is initialized with a proper $paymentMethod, we can instead use it to set up the gateway
        // as a checkout method. The used id below is in such cases instead transformed into UUID's (MAPI).
        $this->id = 'resursbank';

        // The values for title and description is also changed when payment-methods from Resurs is used.
        $this->method_title = 'Resurs Bank AB';
        $this->method_description = 'Resurs Bank Gateway';
        $this->title = 'Resurs Bank AB';

        // Default state for the gateway. If this is disabled, the payment method will be disabled as well.
        // This setting no longer controls the payment method state since the payment methods are normally
        // handled from Merchant/Store-Admin. This also makes it possible to not display single payment methods
        // in the payments tab at wp-admin. In future releases this also makes it a bit easier to move
        // configuration arrays around in the platform.
        //
        // WooCommerce validates this value internally with a "yes" as true, so at this stage we can't give the boolean
        // to the gateway.
        $this->enabled = Enabled::getData();

        // The has-fields setup is normally used to indicate that more fields are available on the payment method
        // in the checkout page.
        $this->has_fields = true;

        // Since this gateway is built to handle many payment methods from one class, we need to make sure that
        // the specific payment method has their own properties that is not based on the gateway setup.
        // This is built up from "getPaymentMethods".
        $this->setPaymentMethodInformation(paymentMethod: $paymentMethod);

        $this->setFilters();
        $this->setActions();
    }

    /**
     * This section is used by the WC Payment Gateway-toggler. If we decide to support "gateway toggling", this
     * section has to be used. See @todo's below.
     *
     * @param $key
     * @param $value
     * @return bool
     * @todo Due to the way we handle our configuration, this method has no actual effect when executed.
     * @todo Eventually, we need to adjust the way woocommerce arrays are updating the values by keys.
     */
    public function update_option($key, $value = '')
    {
        // @todo Using this setup to toggle gateway on/off in the woo-admin panel, will remove
        // @todo the gateway entirely from the list of gateways. That is not what we want, so this is
        // @todo temporarily disabled.
        //if ($key === 'enabled') {
        //    return Enabled::setData($value);
        //}
        return parent::update_option($key, $value);
    }

    /**
     * @return bool
     */
    public function isAvailableOutsideBorders(): bool
    {
        return in_array(
            $this->paymentMethodInformation->type,
            haystack: [
                Type::CREDIT_CARD,
                Type::DEBIT_CARD,
                Type::MASTERPASS,
                Type::PAYPAL,
                Type::CARD
            ],
            strict: true
        );
    }

    /**
     * Initializer. It is not until we have payment method information we can start using this class for real.
     * @param PaymentMethod|null $paymentMethod
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    private function setPaymentMethodInformation(PaymentMethod $paymentMethod = null)
    {
        // Generic setup regardless of payment method.
        $this->setPaymentApiData();

        if ($paymentMethod instanceof PaymentMethod) {
            // Collect the entire payment method information.
            $this->paymentMethodInformation = $paymentMethod;
            $this->id = self::PREFIX . '_' . $this->paymentMethodInformation->id;
            $this->payment_method = $this->id;
            $this->title = $this->paymentMethodInformation->name ?? '';
            $this->method_description = '';
            $this->icon = $this->getMethodIconUrl();

            // Applicant post data should also be collected, so we can re-use it later.
            // The post data arrives in a way that is not always a _REQUEST/_POST/_GET, so this is centralized here.
            // Besides, this is also an escaper.
            $this->applicantPostData = $this->getApplicantPostData();
        }
    }

    /**
     * Generic prepare and setup for API-requests, regardless of payment method.
     * Data added here should always be added to the request.
     *
     * @return void
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setPaymentApiData(): void
    {
        if (!empty(Data::getPaymentMethodBySession())) {
            // Store this for later use, when the primary processing is done.
            $this->apiData['paymentMethod'] = Data::getPaymentMethodBySession();
        }
        $this->apiDataId = sha1(uniqid('wc-api', true));
        $this->API = new ResursBankAPI();
    }

    /**
     * Decide how to use method icons in the checkout.
     *
     * @return string
     * @since 0.0.1.0
     */
    private function getMethodIconUrl()
    {
        $return = null;

        // Make sure that the payment method is properly instantiated before using it.
        if (!empty($this->paymentMethodInformation)) {
            // The filter we're calling is used internally from PluginHooks (method getMethodIconByContent).
            // Urls to a proper image is built from there if the images are properly included in this package.
            if (($icon = $this->getIconByFilter())) {
                $return = $icon;
            }
        }

        return $return;
    }

    /**
     * If a payment requires a specific icon which is not included in this package, this is the place to set it.
     *
     * @return mixed
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     * @todo Decide whether filter for extra icons/logos should be allowed or not.
     */
    private function getIconByFilter()
    {
        return WordPress::applyFilters(
            'getPaymentMethodIcon',
            null,
            $this->paymentMethodInformation
        );
    }

    /**
     * Post data from the simplified post fields with applicant information.
     * We use this method to keep the data collected properly and in the same time sanitized with proper
     * WordPress rules.
     *
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getApplicantPostData(): array
    {
        $realMethodId = $this->getRealMethodId();
        $return = [];
        // Skip the scraping if this is not a payment.
        if ($this->isPaymentReady()) {
            $saneRequest = Data::getSanitizedRequest($_REQUEST ?? []);
            foreach ($saneRequest as $requestKey => $requestValue) {
                if (preg_match(sprintf('/%s$/', $realMethodId), $requestKey)) {
                    $applicantDataKey = sanitize_text_field(
                        (string)preg_replace(
                            sprintf(
                                '/%s_(.*?)_%s/',
                                Data::getPrefix(),
                                $realMethodId
                            ),
                            '$1',
                            $requestKey
                        )
                    );
                    $return[$applicantDataKey] = $requestValue;
                }
            }
        }

        return $return;
    }

    /**
     * The payment method id. The real ID, used for checkout, for which we also tell WooCommerce which gateway
     * to use.
     * @return string
     */
    private function getRealMethodId(): string
    {
        // Return the real id for MAPI if MAPI method is initialized, without woocommerce based extra strings
        // added to it.
        return $this->paymentMethodInformation->id ?? $this->id;
    }

    /**
     * Validate if payment is ready. WC-style.
     *
     * @return bool
     * @since 0.0.1.0
     */
    private function isPaymentReady(): bool
    {
        return (isset($_REQUEST['payment_method'], $_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'checkout');
    }

    /**
     * Prepare filters that WooCommerce may want to throw at us.
     * @since 0.0.1.0
     */
    private function setFilters()
    {
        if (Enabled::isEnabled()) {
            add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
        }
    }

    /**
     * Prepare actions that WooCommerce may want to throw at us.
     * @since 0.0.1.0
     */
    private function setActions()
    {
        add_action('woocommerce_api_resursdefault', [$this, 'getApiRequest']);
        if (Enabled::isEnabled()) {
            // If we have any header scripts, they should be loaded through this action.
            add_action('wp_enqueue_scripts', [$this, 'getHeaderScripts'], 0);
        }
    }

    /**
     * Enqueue scripts that needs to be loaded in the header.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getHeaderScripts()
    {
        // If we have any header scripts again, they should be loaded through this action.
    }

    /**
     * Get specific payment method information.
     * @param $key
     * @return null|mixed
     * @since 0.0.1.0
     * @todo Reflect MAPI.
     */
    private function getMethodInformation($key)
    {
        return $this->paymentMethodInformation->{$key} ?? null;
    }

    /**
     * Get payment method type from current payment method.
     * @return null
     * @since 0.0.1.0
     */
    public function getType()
    {
        return $this->getMethodInformation('type');
    }

    /**
     * Get current order.
     *
     * @return WC_Order
     * @since 0.0.1.0
     */
    public function getOrder(): WC_Order
    {
        return $this->order;
    }

    /**
     * Used by WooCommerce to get the title of the payment method.
     *
     * @return string
     */
    public function get_title(): string
    {
        return parent::get_title();
    }

    /**
     * Set up customer data for the order.
     * @return Customer
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     * @throws Exception
     */
    private function getCustomer(): Customer
    {
        $customerInfoFrom = isset($_REQUEST['ship_to_different_address']) ? 'shipping' : 'billing';
        $governmentId = Data::getCustomerType() === CustomerType::NATURAL ? $this->getCustomerData('government_id') :
            $this->getCustomerData('applicant_government_id');

        if (empty($governmentId) && CustomerRepository::getSsnData() !== null) {
            $governmentId = CustomerRepository::getSsnData();
        }

        // $this->getCustomerData('phone')
        // $this->getCustomerData('contact_government_id')
        return new Customer(
            deliveryAddress: new Address(
                addressRow1: $this->getCustomerData('address_1', $customerInfoFrom),
                postalArea: $this->getCustomerData('city', $customerInfoFrom),
                postalCode: $this->getCustomerData('postcode', $customerInfoFrom),
                countryCode: CountryCode::from($this->getCustomerData('country', $customerInfoFrom)),
                fullName: $this->getCustomerData('full_name', $customerInfoFrom),
                firstName: $this->getCustomerData('first_name', $customerInfoFrom),
                lastName: $this->getCustomerData('last_name', $customerInfoFrom),
                addressRow2: $this->getCustomerData('address_2', $customerInfoFrom),
            ),
            customerType: Data::getCustomerType(),
            contactPerson: $this->getCustomerData('full_name', $customerInfoFrom),
            email: $this->getCustomerData('email'),
            governmentId: $governmentId,
            mobilePhone: $this->getCustomerData('mobile'),
            deviceInfo: new DeviceInfo(
                ip: $_SERVER['REMOTE_ADDR'],
                userAgent: $_SERVER['HTTP_USER_AGENT']
            )
        );
    }

    /**
     * Fetch proper customer data from applicant form request.
     *
     * @param string $key
     * @param string $returnType
     * @return string
     * @since 0.0.1.0
     * @noinspection PhpArrayIsAlwaysEmptyInspection
     */
    private function getCustomerData(string $key, string $returnType = 'billing'): string
    {
        // Primarily, this data has higher priority over internal data as this is based on custom fields.
        // applicantPostData has been sanitized prior to this point.
        $return = $this->applicantPostData[$key] ?? '';

        // If it's not in the post data, it could possibly be found in the order maintained from the order.
        $billingAddress = $this->order->get_address();
        $deliveryAddress = $this->order->get_address('shipping');

        $customerInfo = $billingAddress;
        if ($returnType === 'shipping' || $returnType === 'delivery') {
            $customerInfo = $deliveryAddress;
        }

        if (isset($customerInfo[$key])) {
            $return = $customerInfo[$key];
        }

        // Mobile is usually not included in WooCommerce fields, so the return value is still empty here,
        // we should fetch mobile from billing phone field instead.
        if ($key === 'mobile' && !$return && isset($customerInfo['phone'])) {
            $return = $customerInfo['phone'];
        }

        // Magic for full name.
        if ($key === 'full_name') {
            // Full name is a merge from first and last name. It's made up but sometimes necessary.
            $return = sprintf('%s %s', $this->getCustomerData('first_name'), $this->getCustomerData('last_name'));
        }

        return (string)$return;
    }

    /**
     * Prepare order for signing-rules. Build success/fail/back urls.
     * @return void
     * @throws Exception
     * @since 0.0.1.0
     * @todo This relates to ecom1 and should be handled elsewhere in MAPI.
     */
    private function setSigning(): void
    {
        $successUrl = $this->getSigningUrl(['success' => true, 'urlType' => 'success']);
        $failUrl = $this->getSigningUrl(['success' => false, 'urlType' => 'fail']);
        $backUrl = $this->getSigningUrl(['success' => false, 'urlType' => 'back']);

        $this->apiData['successUrl'] = $successUrl;
        $this->apiData['failUrl'] = $failUrl;
        $this->apiData['backUrl'] = $backUrl;

        $this->API->getConnection()->setSigning(
            $successUrl,
            $failUrl,
            false,
            $backUrl
        );

        $this->setLoggedSigningUrl('success', $successUrl);
        $this->setLoggedSigningUrl('fail', $failUrl);
        $this->setLoggedSigningUrl('back', $backUrl);
        $this->setOrderCheckoutMeta($this->order);
    }

    /**
     * Log signing event.
     *
     * @param $type
     * @param $url
     * @since 0.0.1.7
     */
    private function setLoggedSigningUrl($type, $url)
    {
        Data::writeLogEvent(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s %s',
                $type,
                $url
            )
        );
    }

    /**
     * Generate signing url for success/thank you page.
     *
     * @param null $params
     * @return string
     * @since 0.0.1.0
     */
    private function getSigningUrl($params = null): string
    {
        $wcApi = WooCommerce::getWcApiUrl();
        $signingBaseUrl = add_query_arg('apiDataId', $this->apiDataId, $wcApi);
        $signingBaseUrl = add_query_arg('apiData', $this->getApiData((array)$params, true), $signingBaseUrl);

        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                __('Base URL for signing: %s', 'resurs-bank-payments-for-woocommerce'),
                $signingBaseUrl
            )
        );
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                __('Signing parameters for %s: %s', 'resurs-bank-payments-for-woocommerce'),
                $signingBaseUrl,
                print_r($this->getApiData((array)$params), true)
            )
        );

        return $signingBaseUrl;
    }

    /**
     * @param null $addArray
     * @param null $encode
     * @return string
     * @since 0.0.1.0
     */
    private function getApiData($addArray = null, $encode = null): string
    {
        $return = json_encode(array_merge((array)$addArray, $this->apiData));

        if ((bool)$encode) {
            $return = Strings::base64urlEncode($return);
        }

        return (string)$return;
    }

    /**
     * Generic method to handle all metadata that is generated on the fly through process_payment.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setOrderCheckoutMeta($order)
    {
        Data::setOrderMeta($order, 'apiDataId', $this->apiDataId);
        Data::setOrderMeta($order, 'orderSigningPayload', $this->getApiData());
        Data::setOrderMeta($order, 'resursReference', $this->getOrderReference());
        if (!empty(Data::getPaymentMethodBySession())) {
            Data::setOrderMeta($order, 'paymentMethod', Data::getPaymentMethodBySession());
        }
    }

    /**
     * Resolve payment id to use at Resurs Bank. If the order has not yet been created, use a temporary ID
     * which will later be replaced by the order id.
     *
     * @param WC_Order|null $order
     * @return string
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     */
    private function getOrderReference(null|WC_Order $order = null): string
    {
        $return = 0;

        if ($order instanceof WC_Order) {
            $return = $order->get_id();
        }

        if (!is_int($return) || $return === 0) {
            $return = sha1(uniqid('payment', true));
        }

        return $return;
    }

    /**
     * @return ResursDefault
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     * @todo This is related to ecom1 and handles storeId data in the payload. This is integrated in MAPI
     * @todo and should no longer be necessary.
     */
    private function setStoreId(): ResursDefault
    {
        $deprecatedStoreId = WordPress::applyFiltersDeprecated('set_storeid', null);
        $storeId = WordPress::applyFilters('setStoreId', $deprecatedStoreId);
        if (!empty($storeId)) {
            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    '%s: %s',
                    __FUNCTION__,
                    $storeId
                )
            );

            $this->API->getConnection()->setStoreId($storeId);
        }

        return $this;
    }

    /**
     * @return $this
     * @since 0.0.1.0
     * @todo Set meta data when meta data is available in MAPI for externalCustomerId.
     */
    private function setCustomerId(): self
    {
        $customerId = $this->getCustomerId();
        if ($customerId) {
            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    '%s: %s',
                    __FUNCTION__,
                    $customerId
                )
            );
        }
        return $this;
    }

    /**
     * Get the customer id as stored in WooCommerce / WordPress.
     *
     * @return int
     * @since 0.0.1.0
     */
    private function getCustomerId(): int
    {
        $return = 0;
        // Always try to get the WP member user id first.
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
        } else {
            // On failures, try this method. In the end, the methods is mostly the same, but for
            // different versions.
            $current_user = get_currentuserinfo();
        }

        // Extract the ID. If exists.
        if (isset($current_user, $current_user->ID) && $current_user !== null) {
            $return = $current_user->ID;
        }
        // Created orders has higher priority since this id might have been created during order processing.
        // So if the ID already exists in the order, use this instead.
        if (!empty($this->order) && method_exists($this->order, 'get_user_id')) {
            $orderUserId = $this->order->get_user_id();
            if ($orderUserId) {
                $return = $orderUserId;
            }
        }

        return $return;
    }

    /**
     * Get payment full method information object from ecom data.
     *
     * @return string
     * @since 0.0.1.0
     */
    private function getPaymentMethod(): string
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return (string)isset($this->paymentMethodInformation->id) ? $this->paymentMethodInformation->id : '';
    }

    /**
     * @param OrderLineType $orderLineType
     * @param WC_Product $productData
     * @param array $wcProductItem Product item details from WooCommerce, contains the extended data that can't be found in WC_Product.
     * @return OrderLine
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public function getProductRow(
        OrderLineType $orderLineType,
        WC_Product $productData,
        array $wcProductItem
    ): OrderLine {
        return new OrderLine(
            quantity: $wcProductItem['quantity'],
            quantityUnit: $this->getFromProduct(
                getValueType: 'quantityUnit',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            vatRate: $this->getFromProduct(
                getValueType: 'vatRate',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            totalAmountIncludingVat: $this->getFromProduct(
                getValueType: 'totalAmountIncludingVat',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            description: $this->getFromProduct(
                getValueType: 'title',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            reference: $this->getFromProduct(
                getValueType: 'reference',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            type: $orderLineType,
            unitAmountIncludingVat: $this->getFromProduct(
                getValueType: 'unitAmountIncludingVat',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            totalVatAmount: $this->getFromProduct(
                getValueType: 'totalVatAmount',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            )
        );
    }

    /**
     * Add an order line that is not based on pre-defined product data.
     *
     * @param OrderLineType $orderLineType
     * @param string $description
     * @param string $reference
     * @param float $unitAmountIncludingVat
     * @param float $vatRate
     * @param int $quantity
     * @return OrderLine
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     * @throws FilesystemException
     * @throws TranslationException
     */
    public function getCustomOrderLine(
        OrderLineType $orderLineType,
        string $description,
        string $reference,
        float $unitAmountIncludingVat,
        float $vatRate,
        int $quantity = 1
    ): OrderLine {
        $totalAmountIncludingVat = $unitAmountIncludingVat * $quantity;
        $totalVatAmount = $totalAmountIncludingVat - ($totalAmountIncludingVat / (1 + ($vatRate / 100)));
        return new OrderLine(
            quantity: (float)$quantity,
            quantityUnit: Translator::translate('default-quantity-unit'),
            vatRate: $vatRate,
            totalAmountIncludingVat: $totalAmountIncludingVat,
            description: $description,
            reference: $reference,
            type: $orderLineType,
            unitAmountIncludingVat: $unitAmountIncludingVat,
            totalVatAmount: $totalVatAmount
        );
    }

    /**
     * Fetch information about articles from WooCommerce.
     *
     * @param string $getValueType
     * @param WC_Product $productObject
     * @param array $wcProductItemData
     * @return float|int|string
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws TranslationException
     * @throws ReflectionException
     * @since 0.0.1.0
     */
    protected function getFromProduct(
        string $getValueType,
        WC_Product $productObject,
        array $wcProductItemData = []
    ): float|int|string {
        $return = '';

        // $wcProductItemData always returns same information for at product. The data here can always be expected
        // based on the content from WC_Order_Item (see $extra_data in WC_Order_Item).

        switch ($getValueType) {
            case 'reference':
                $return = $this->getProperArticleNumber($productObject);
                break;
            case 'description':
            case 'title':
                $return = !empty($useTitle = $productObject->get_title()) ? $useTitle : __(
                    'Article description is missing.',
                    'resurs-bank-payments-for-woocommerce'
                );
                break;
            case 'unitAmountIncludingVat':
                $return = wc_get_price_including_tax($productObject);
                break;
            case 'totalAmountIncludingVat':
                $return = wc_get_price_including_tax($productObject, ['qty' => $wcProductItemData['quantity']]);
                break;
            case 'unitAmountWithoutVat':
                // Special reflection of what Resurs Bank wants.
                $return = wc_get_price_excluding_tax($productObject);
                break;
            case 'totalVatAmount':
                $return = wc_get_price_including_tax($productObject,
                        ['qty' => $wcProductItemData['quantity']]) - wc_get_price_excluding_tax(
                        $productObject,
                        ['qty' => $wcProductItemData['quantity']]
                    );
                break;
            case 'vatRate':
                $return = $this->getProductVat($productObject);
                break;
            case 'quantityUnit':
                // Using default measure from ECom for now.
                $return = Translator::translate('default-quantity-unit');
                break;
            default:
                if (method_exists($productObject, sprintf('get_%s', $getValueType))) {
                    $return = $productObject->{sprintf('get_%s', $getValueType)}();
                }
                break;
        }

        return $return;
    }

    /**
     * Different way to fetch article numbers.
     * @param WC_Product $product
     * @return mixed
     * @since 0.0.1.0
     */
    private function getProperArticleNumber(WC_Product $product): mixed
    {
        return WooCommerce::getProperArticleNumber($product);
    }

    /**
     * @param WC_Product $product
     * @return float|int
     * @since 0.0.1.0
     */
    private function getProductVat(WC_Product $product): float|int
    {
        $taxClass = $product->get_tax_class();
        $ratesArray = WC_Tax::get_rates($taxClass);

        $rates = array_shift($ratesArray);
        if (isset($rates['rate'])) {
            $return = (double)$rates['rate'];
        } else {
            $return = 0;
        }

        return $return;
    }

    /**
     * Decide if the payment gateway is available or not. Work both in admin and checkouts, so this is where
     * we also need to check out conditions from the early instantiated cart.
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    public function is_available(): bool
    {
        global $woocommerce;

        // If the payment method information is not initialized properly, it should be not in use.
        if (!isset($this->paymentMethodInformation) || !$this->paymentMethodInformation instanceof PaymentMethod) {
            return false;
        }

        // This feature is primarily for the storefront.
        $return = parent::is_available();

        /**
         * The cart check has a known issue.
         * @link https://wordpress.org/support/topic/php-notice-trying-to-get-property-total-of-non-object-2/
         */

        // If there's no cart, and we miss get_order_total in this gateway this instance probably do not belong
        // to the storefront.
        if (!isset($woocommerce->cart) ||
            !method_exists($this, method: 'get_order_total') ||
            !isset($this->paymentMethodInformation->id)
        ) {
            // Return false if gateway in Resurs-admin is disabled and stop running the full process here.
            if (Enabled::isEnabled()) {
                return false;
            }
        }
        $customerType = Data::getCustomerType();

        // If this feature is not missing the method, we now know that there is chance that we're
        // located in a checkout. We will at this moment run through the min-max amount that resides
        // in each payment method that is requested here. If the payment method is not present,
        // this one will be skipped and the rest of the function will fail over to the parent value.
        if (isset($this->paymentMethodInformation, $this->paymentMethodInformation->minLimit)) {
            $minMax = ResursBankAPI::getResurs()->getMinMax(
                $this->get_order_total(),
                $this->getRealMin($this->paymentMethodInformation->minLimit),
                $this->getRealMax($this->paymentMethodInformation->maxLimit)
            );
            if (!$minMax) {
                $return = false;
            }

            // We decide at this level if the payment method should be available,
            // based on current chosen country. Beware of the admin parts.
            if ($return && !empty($customerType) && !is_admin()) {
                $return = in_array(
                    $customerType,
                    (array)$this->paymentMethodInformation->customerType,
                    true
                );
            }
        }

        return $return;
    }

    /**
     * Customize minimum allowed amount for a payment method. Can never be lower than the lowest minimum from method.
     *
     * @param float $minLimit
     * @return float
     * @link https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/42
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     * @todo Make sure this works as ecom2 works with float instead of int and WooCommerce tend to not do the same.
     */
    private function getRealMin(float $minLimit): float
    {
        $requestedMinLimit = WordPress::applyFilters(
            'methodMinLimit',
            $minLimit,
            $this->paymentMethodInformation
        );

        if ($requestedMinLimit < $this->paymentMethodInformation->minLimit) {
            $requestedMinLimit = $this->paymentMethodInformation->minLimit;
        }

        return (float)$requestedMinLimit;
    }

    /**
     * Customize maximum allowed amount for a payment method. Can never be higher than the highest maximum from method.
     *
     * @param float $maxLimit
     * @return float
     * @link https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/42
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     * @todo Make sure this works as ecom2 works with float instead of int and WooCommerce tend to not do the same.
     */
    private function getRealMax(float $maxLimit): float
    {
        $requestedMaxLimit = WordPress::applyFilters(
            'methodMaxLimit',
            $maxLimit,
            $this->paymentMethodInformation
        );

        if ($requestedMaxLimit > $this->paymentMethodInformation->maxLimit) {
            $requestedMaxLimit = $this->paymentMethodInformation->maxLimit;
        }

        return (float)$requestedMaxLimit;
    }

    /**
     * Simplified checkout form field generator. This is WooCommerce-specific inherits for which we render
     * fields required by Resurs.
     *
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     * @todo Utilize ecom2!
     */
    public function payment_fields()
    {
        /* Remember: When we display the fields, we must also make sure that WordPress is the part that sanitize
         * and display the fields. Therefore, we eventually need to tell WordPress further about safe styling.

           add_filter('safe_style_css', function ($styles) {
                $styles[] = 'display';
                return $styles;
            });

         */

        // @todo See the code after the return part. This smaller is just temporary.
        return 'Display "USP" - and eventually on demand also government id fields here.';
    }

    /**
     * If this payment method is Resurs bank internal, this will return true.
     * @param $paymentMethod
     * @return bool
     */
    public static function isInternalMethod($paymentMethod): bool
    {
        return isset($paymentMethod->type) && str_starts_with($paymentMethod->type, 'RESURS_');
    }

    /**
     * @param $fieldName
     * @return bool
     * @since 0.0.1.0
     */
    private function getDisplayableField($fieldName): bool
    {
        return !(Data::getResursOption('streamline_payment_fields') ||
            !FormFields::canDisplayField($fieldName));
    }

    /**
     * The WooCommerce-inherited process_payment method. This is where we normally want to place our
     * payment actions.
     *
     * @param $order_id
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public function process_payment($order_id): array
    {
        $order = new WC_Order($order_id);
        $this->order = $order;

        if (empty(Data::getOrderMeta('paymentMethodInformation', $order))) {
            $paymentMethodInformation = Data::getPaymentMethodById(Data::getPaymentMethodBySession());
            if (is_object($paymentMethodInformation)) {
                Data::setOrderMeta($order, 'paymentMethodInformation', json_encode($paymentMethodInformation));
            }
        }
        $this->setOrderCheckoutMeta($order_id);
        // Used by WooCommerce from class-wc-checkout.php to identify the payment method.
        $order->set_payment_method(Data::getPaymentMethodBySession());

        // Prepare API data and metas that applies to all orders and all flows.
        $this->preProcessOrder($order);
        return $this->processResursOrder($order);
    }

    /**
     * @param WC_Order $order
     * @param string $result
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private function getReturnUrl(WC_Order $order, string $result = 'failure'): string
    {
        return $result === 'success' ? $this->get_return_url($order) : html_entity_decode($order->get_cancel_order_url());
    }

    /**
     * Prepare stuff before the process actions. Helper to find proper order id's during and after API-calls.
     *
     * @param WC_Order $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private function preProcessOrder(WC_Order $order): void
    {
        $this->apiData['wc_order_id'] = $order->get_id();
        $this->apiData['preferred_id'] = $this->getOrderReference();
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                'setPreferredId:%s',
                $this->apiData['preferred_id']
            )
        );
    }

    /**
     * This is where we used to handle separate flows. As we will only have two in the future,
     * this will be easier.
     * @param WC_Order $order
     * @return array
     * @throws Exception
     */
    private function processResursOrder(WC_Order $order): array
    {
        // Defaults returning to WooCommerce if not successful.
        $return = [
            'result' => 'failure',
            'redirect' => $this->getReturnUrl($order),
        ];

        // @todo Add meta data at Resurs with WooCommerce order id ($order->get_id()).

        try {
            // Order Creation
            $paymentResponse = PaymentRepository::create(
                storeId: StoreId::getData(),
                paymentMethodId: $this->getPaymentMethod(),
                orderLines: $this->getOrderLinesMapi(),
                orderReference: $order->get_id(),
                application: $this->getApplication(),
                customer: $this->getCustomer(),
                options: $this->getOptions($order)
            );
            $return = $this->getReturnResponse(
                createPaymentResponse: $paymentResponse,
                return: $return,
                order: $order
            );
            // This is our link to the payment at Resurs for which we save the uuid we get at the create.
            // At callback level, this is the reference we look for, to re-match the WooCommerce order id.
            Metadata::setOrderMeta(
                order: $order,
                metaDataKey: ResursDefault::PREFIX . '_order_reference',
                metaDataValue: $paymentResponse->id
            );
        } catch (Exception $createPaymentException) {
            // In case we get an error from any other component than the create, we need to rewrite this response.
            $return = [
                'result' => 'failure',
                'redirect' => $this->getReturnUrl($order)
            ];
            // Add note to notices and write to log.
            $order->add_order_note(
                $createPaymentException->getMessage()
            );
            Config::getLogger()->error($createPaymentException);
            // Add on-screen message from failure.
            wc_add_notice($createPaymentException->getMessage(), 'error');
        }

        return $return;
    }

    /**
     * @return Application
     * @todo Temporary solution, until MAPI stops require the Application block.
     */
    private function getApplication(): Application
    {
        return new Application(
            requestedCreditLimit: null,
            applicationData: null
        );
    }

    /**
     * @param WC_Order $order
     * @return Options
     * @throws IllegalValueException
     */
    private function getOptions(WC_Order $order): Options
    {
        // @todo Defaults like manual inspection, frozen payments, etc should be changed to configurable options
        // @todo through the admin panel.
        return new Options(
            initiatedOnCustomerDevice: true,
            handleManualInspection: false,
            handleFrozenPayments: false,
            redirectionUrls: new RedirectionUrls(
                customer: new ParticipantRedirectionUrls(
                    failUrl: $this->getReturnUrl($order, result: 'failure'),
                    successUrl: $this->getReturnUrl($order, result: 'success')
                ),
                coApplicant: null,
                merchant: null
            ),
            callbacks: new Callbacks(
                authorization: new Callback(
                    url: $this->getCallbackUrl(callbackType: CallbackType::AUTHORIZATION),
                    description: 'Authorization callback'
                ),
                management: new Callback(
                    url: $this->getCallbackUrl(callbackType: CallbackType::MANAGEMENT),
                    description: 'Management callback'
                ),
            ),
            timeToLiveInMinutes: 120,
        );
    }

    /**
     * Generate URL for MAPI callbacks.
     * Note: We don't have to apply the order id to the callback URL, as the callback will be sent back as a POST (json).
     *
     * @param CallbackType $callbackType
     * @return string
     */
    private function getCallbackUrl(CallbackType $callbackType): string
    {
        // @todo Switch getWcApiUrl to utils.
        return Url::getQueryArg(
            baseUrl: WooCommerce::getWcApiUrl(),
            arguments: [
                'mapi-callback' => $callbackType->value,
            ]
        );
    }

    /**
     * Convert createPaymentResponse to a WooCommerce reply.
     * @param Payment $createPaymentResponse
     * @param array $return
     * @param WC_Order $order
     * @return array
     */
    private function getReturnResponse(Payment $createPaymentResponse, array $return, WC_Order $order): array
    {
        // At this point, the return array is set to failure, so if the response is not a success, we can just return.
        // Frozen orders are normally accepted as successful responses.
        switch ($createPaymentResponse->status) {
            case Status::FROZEN:
            case Status::ACCEPTED:
                $return['result'] = 'success';
                $return['redirect'] = $this->getReturnUrl($order, 'success');
                break;
            case Status::TASK_REDIRECTION_REQUIRED:
                $return['result'] = 'success';
                $return['redirect'] = $createPaymentResponse->taskRedirectionUrls->customerUrl;
                break;
            default:
        }

        return $return;
    }

    /**
     * This is where we handle all API calls from the outside (Resurs).
     *
     * @throws Exception
     */
    public function getApiRequest()
    {
        if (isset($_REQUEST['mapi-callback']) &&
            is_string($_REQUEST['mapi-callback'])
        ) {
            $response = [
                'success' => false,
                'message' => ''
            ];

            // Callback will respond and exit.
            try {
                $response['success'] = CallbackModule::processCallback(
                    callbackType: CallbackType::from(
                        value: strtoupper($_REQUEST['mapi-callback'])
                    )
                );
            } catch (Error|Exception $e) {
                Config::getLogger()->error($e);
                $response['message'] = $e->getMessage();
            }

            // @todo We used $responseController to reply with JSON before. Since we need a customized response code
            // @todo this must be fixed again.
            header(header: 'Content-Type: application/json',response_code: $response['success'] ? 202 : 408);
        }

        exit;
    }

    /**
     * Get API data from session.
     *
     * @param $apiRequestContent
     * @param $requestArray
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @todo Clarify what this actually do, and sanitize the data when added.
     */
    private function getSessionApiData($apiRequestContent, $requestArray)
    {
        foreach ($requestArray as $itemKey => $fromItemKey) {
            $sessionValue = WooCommerce::getSessionValue($fromItemKey);
            if ((!empty($sessionValue) && !isset($apiRequestContent[$itemKey])) ||
                empty($apiRequestContent[$itemKey])
            ) {
                $apiRequestContent[$itemKey] = $sessionValue;
            }
        }
        return $apiRequestContent;
    }

    /**
     * Prepare the API data to be sent, by merging it here. Destination is normally any.
     *
     * @param $apiArray
     * @return $this
     * @since 0.0.1.0
     */
    private function setApiData($apiArray): self
    {
        $this->apiData = array_merge($this->apiData, $apiArray);
        return $this;
    }

    /**
     * @return WC_Order
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getCurrentOrder(): WC_Order
    {
        $return = new WC_Order($this->getApiValue('wc_order_id'));

        // Woocommerce flag here, to make sure things happens the way woocommerce want it.
        $awaitingOrderId = WooCommerce::getSessionValue('order_awaiting_payment');
        if ($awaitingOrderId && !$return->get_id()) {
            $return = new WC_Order($awaitingOrderId);
        }

        return $return;
    }

    /**
     * Get value from API requested array.
     *
     * @param $key
     * @return mixed|string
     * @since 0.0.1.0
     */
    public function getApiValue($key): mixed
    {
        // If key is empty or not found, return the whole set.
        return (string)isset($this->apiData[$key]) ? $this->apiData[$key] : $this->apiData;
    }

    /**
     * Very much self explained.
     * @param $woocommerceStatus
     * @param $statusNotification
     * @throws Exception
     * @since 0.0.1.0
     * @todo Make sure this works with MAPI. Also make sure that we take height for $this->>order->payment_complete.
     */
    private function updateOrderStatus($woocommerceStatus, $statusNotification)
    {
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf('Status: %s, Message: %s', $woocommerceStatus, $statusNotification)
        );

        if ($woocommerceStatus === self::STATUS_FINALIZED) {
            $this->order->payment_complete();
            WooCommerce::setOrderNote(
                $this->order,
                $statusNotification
            );
        } else {
            $currentOrderStatus = $this->order->get_status();
            if ($currentOrderStatus !== $woocommerceStatus) {
                OrderStatus::setOrderStatusByQueue($this->order);
            } else {
                $orderStatusUpdateNotice = __(
                    sprintf(
                        '%s notice: Request to set order to status "%s" but current status is already set.',
                        __FUNCTION__,
                        $woocommerceStatus
                    ),
                    'resurs-bank-payments-for-woocommerce'
                );
                WooCommerce::setOrderNote(
                    $this->order,
                    $orderStatusUpdateNotice
                );
                Data::writeLogNotice($orderStatusUpdateNotice);
            }
        }

        $this->order->save();
    }

    /**
     * Generate cancel notes, for signings.
     *
     * @return string
     * @todo Move this to where we handle failures.
     */
    private function getCancelNotice(): string
    {
        return 'Customer returned: Failed or cancelled payment.';
    }

    /**
     * @return OrderLineCollection
     * @throws IllegalTypeException
     */
    private function getOrderLinesMapi(): OrderLineCollection
    {
        if (WooCommerce::getValidCart()) {
            $orderHandler = new OrderHandler();
            return $orderHandler->getOrderLines();
        }

        // todo: Translate this via ecom2.
        throw new RuntimeException(
            __('Cart is currently unavailable.')
        );
    }
}
