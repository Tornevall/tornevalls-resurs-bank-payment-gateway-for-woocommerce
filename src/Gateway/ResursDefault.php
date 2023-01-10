<?php

// We do use camel cases in this file.

/** @noinspection PhpCSValidationInspection */
/** @noinspection EfferentObjectCouplingInspection */
/** @noinspection PhpAssignmentInConditionInspection */

namespace ResursBank\Gateway;

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
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Customer;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use ResursBank\Module\Callback as CallbackModule;
use ResursBank\Module\Data;
use ResursBank\Module\ResursBankAPI;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Modules\Payment\Converter\Cart;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;
use WC_Product;
use WC_Session_Handler;
use WC_Tax;
use WP_Post;
use function in_array;
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
     * This prefix is used for various parts of the settings by WooCommerce,
     * for example, as an ID for these settings, and as a prefix for the values
     * in the database. The prefix is also used as an identifier for this gateway.
     */
    public const PREFIX = 'resursbank';

    /**
     * Default identifier title for this gateway.
     */
    public const TITLE = 'Resurs Bank AB';

    /**
     * @var WC_Order $order
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
     * Data that will be sent between Resurs Bank and ourselves. This array will be merged into base64-encoded strings
     * to maintain the charset integrity.
     *
     * @var array $apiData
     * @since 0.0.1.0
     */
    private array $apiData = [];

    /**
     * This instance payment method from Resurs Bank.
     * @var PaymentMethod|null $paymentMethodInformation
     */
    private ?PaymentMethod $paymentMethodInformation = null;

    /**
     * ResursDefault constructor.
     *
     * @param PaymentMethod|null $resursPaymentMethod Making sure the gateway is reachable even if initialization has failed.
     * @throws Exception
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function __construct(
        public readonly ?PaymentMethod $resursPaymentMethod = null
    ) {
        // A proper ID must always exist regardless of the init outcome. However, it should be set after the init
        // as the order view may want to use it differently.
        $this->id = $this->getProperGatewayId($resursPaymentMethod);

        // Do not verify if this sections is allowed to initialize. It has to initialize itself each time this
        // class is called, even if the payment method itself is null (API calls is still depending on its existence).
        $this->initializePaymentMethod(paymentMethod: $resursPaymentMethod);
    }

    /**
     * Method to properly fetch an order if it is present in a current "view".
     * @return WC_Order|null
     * @throws ConfigException
     * @noinspection SpellCheckingInspection
     * @todo WOO-960 - There is a problem somewhere related to dashboard or similar that makes us unable to
     * @todo WOO-960 - get access to an order in the internal order view. This method solves this problem temporarily
     * @todo WOO-960 - but should not be forced to use $_GET. It has to be changed to be safe.
     */
    private function getOrder(): WC_Order|null
    {
        global $theorder;
        $post = get_post();

        $return = null;

        try {
            if (isset($theorder)) {
                $return = $theorder;
            } elseif (isset($post) && $post instanceof WP_Post && $post->post_type === 'shop_order') {
                $return = new WC_Order($post->ID);
            } elseif (isset($_GET) && isset($_GET['post']) && is_string($_GET['post'])) {
                Config::getLogger()->warning(
                    message: 'OrderView is currently using $_GET to reach the current order (emergency fallback).'
                );
                $return = new WC_Order($_GET['post']);
            }
        } catch (Throwable $e) {
            Config::getLogger()->error($e);
        }

        return $return;
    }

    /**
     * @param PaymentMethod|null $resursPaymentMethod
     * @return string
     * @throws ConfigException
     */
    private function getProperGatewayId(?PaymentMethod $resursPaymentMethod = null): string
    {
        $currentOrder = $this->getOrder();

        // If no PaymentMethod is set at this point, but instead an order, the gateway is considered not
        // located in the checkout but in a maintenance state (like wp-admin/order). In this case, we
        // need to identify the current order as created with Resurs payments. Since WooCommerce is using
        // the gateway id to identify the current payment method, we also need to adapt into the initial
        // id (uuid) that was used when the order was created.
        return !isset($resursPaymentMethod) && isset($currentOrder) && (
            $currentOrder instanceof WC_Order && Metadata::isValidResursPayment($currentOrder)
        ) ? $currentOrder->get_payment_method() : self::PREFIX;
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
        global $woocommerce;

        // Validate a cart if present and put it in the class, so that it can be used for the payment
        // later on. If there is no customer cart, it exists as a nullable in $woocommerce.
        if ($woocommerce->cart instanceof WC_Cart) {
            $this->cart = $woocommerce->cart;
        }

        // The values for title and description is also changed when payment-methods from Resurs is used.
        $this->method_title = 'Resurs Bank AB';
        $this->method_description = 'Resurs Bank Gateway';
        $this->title = self::TITLE;

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

        if ($paymentMethod instanceof PaymentMethod) {
            // Since this gateway is built to handle many payment methods from one class, we need to make sure that
            // the specific payment method has their own properties that is not based on the gateway setup.
            // This is built up from "getPaymentMethods".
            $this->setPaymentMethodInformation(paymentMethod: $paymentMethod);
        }

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
            if ($this->paymentMethodInformation->isResursMethod()) {
                $return = Data::getImage(imageName: 'resurs-logo.png');
            }
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
            $saneRequest = Url::getSanitizedArray(array: $_REQUEST ?? []);
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
    }

    /**
     * Used by WooCommerce to get the title of the payment method.
     *
     * @return string
     */
    public function get_title(): string
    {
        /**
         * $theorder is the correct naming convention according to WC, no spell checking needed.
         * @noinspection SpellCheckingInspection
         */
        // WC Global. Required for parts of this method.
        global $theorder;

        $return = parent::get_title();

        if (!isset($theorder)) {
            return $return;
        }

        // Use defaults if no order exists (this method is used on several places).
        if (!($theorder instanceof WC_Order) || !MetaData::isValidResursPayment($theorder)) {
            return $return;
        }

        // Since this part of the mechanism in wc-order-view is executed on all orders
        // including those that are not created with Resurs, we need to make sure that
        // we only touch orders that belong to us before changing the returned output.
        if (MetaData::isValidResursPayment($theorder)) {
            $return = $theorder->get_payment_method_title();
        }

        return $return;
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

        try {
            $sessionCustomerType = WcSession::getCustomerType();
        } catch (Throwable) {
            // Possible to-do: Make sure that defaults are set by available payment methods, not just NATURAL.
            // Normally, this is not a problem, since the merchant majority is of type NATURAL, so for now we're
            // good to go with this.
            $sessionCustomerType = CustomerType::NATURAL;
        }

        $governmentId = $sessionCustomerType === CustomerType::NATURAL ? $this->getCustomerData('government_id') :
            $this->getCustomerData('applicant_government_id');

        // Since WooCommerce uses a cookie to pick up a session, we can't use the ecom "real" session to fetch the
        // government id.
        if (WC()->session instanceof WC_Session_Handler && empty($governmentId)) {
            $governmentId = WC()->session->get(key: (new Session())->getKey(Repository::SESSION_KEY_SSN_DATA));
        }

        // @todo Also those fields for LEGAL customers.
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
            customerType: $sessionCustomerType,
            contactPerson: $sessionCustomerType === CustomerType::LEGAL ?
                $this->getCustomerData('full_name', $customerInfoFrom) : '',
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
                $return = wc_get_price_including_tax(
                        $productObject,
                        ['qty' => $wcProductItemData['quantity']]
                    ) - wc_get_price_excluding_tax(
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
            $return = (float)$rates['rate'];
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
        if (!isset($this->paymentMethodInformation)) {
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

        try {
            $customerType = WcSession::getCustomerType();
        } catch (Throwable) {
            // Possible to-do: Make sure that defaults are set by available payment methods, not just NATURAL.
            // Normally, this is not a problem, since the merchant majority is of type LEGAL, so for now we're
            // good to go with this.
            $customerType = CustomerType::NATURAL;
        }

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
     * @noinspection PhpUndefinedFieldInspection
     */
    public function payment_fields(): void
    {
        try {
            $usp = PaymentMethodRepository::getUniqueSellingPoint(
                paymentMethod: $this->resursPaymentMethod,
                amount: $this->get_order_total()
            );
            echo $usp->content;
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            echo "";
        }
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
                orderLines: Cart::getOrderLines(),
                orderReference: $order->get_id(),
                customer: $this->getCustomer(),
                options: $this->getOptions($order)
            );
            $return = $this->getReturnResponse(
                createPaymentResponse: $paymentResponse,
                return: $return,
                order: $order
            );

            if (isset($return['result']) && $return['result'] === 'success') {
                // Forget the session variable if there is a success.
                WcSession::unset((new Session())->getKey(key: Repository::SESSION_KEY_SSN_DATA));
                WcSession::unset((new Session())->getKey(key: Repository::SESSION_KEY_CUSTOMER_TYPE));
            }

            // This is our link to the payment at Resurs for which we save the uuid we get at the create.
            // At callback level, this is the reference we look for, to re-match the WooCommerce order id.
            Metadata::setOrderMeta(
                order: $order,
                metaDataKey: 'payment_id',
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
            handleFrozenPayments: true,
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
     * @throws IllegalValueException
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
                // The way we handle callbacks now do not require a boolean the same way as before. Instead, we will
                // just handle exceptions as errors.
                CallbackModule::processCallback(
                    callbackType: CallbackType::from(
                        value: strtoupper($_REQUEST['mapi-callback'])
                    )
                );
                $response['success'] = true;
            } catch (Throwable $e) {
                Config::getLogger()->error($e);
                $response['message'] = $e->getMessage();
            }

            $responseCode = $response['success'] ? 202 : 408;

            Config::getLogger()->debug(message: 'Callback response, code ' . ($response['success'] ? 202 : 408) . '.');
            Config::getLogger()->debug(message: print_r($response, return: true));

            Route::respond(
                body: json_encode($response),
                code: $responseCode
            );
        }

        exit;
    }
}
