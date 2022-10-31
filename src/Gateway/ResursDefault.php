<?php

// We do use camel cases in this file.

/** @noinspection PhpCSValidationInspection */
/** @noinspection EfferentObjectCouplingInspection */
/** @noinspection PhpAssignmentInConditionInspection */

namespace ResursBank\Gateway;

use Exception;
use JsonException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Log\LogLevel;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Module\Payment\Api\Create;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order\OrderLine;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order\OrderLineCollection;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ecommerce\Types\CheckoutType;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursBank\Module\ResursBankAPI;
use ResursBank\Service\OrderHandler;
use ResursBank\Service\OrderStatus;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\Domain;
use TorneLIB\Utils\Generic;
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
 * Default payment method class. Handles payments and orders dynamically, with focus on less loss
 * of data during API communication.
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
     * @var string
     * @since 0.0.1.0
     */
    const TYPE_SIMPLIFIED = 'simplified';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const TYPE_RCO = 'rco';

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
     * @var WC_Cart $cart
     * @since 0.0.1.0
     */
    protected $cart;
    /**
     * @var array $applicantPostData Applicant request.
     * @since 0.0.1.0
     */
    private $applicantPostData = [];
    /**
     * @var stdClass $paymentResponse
     * @since 0.0.1.0
     */
    private stdClass $paymentResponse;
    /**
     * @var array
     * @since 0.0.1.0
     */
    private array $wcOrderData;
    /**
     * Data that will be sent between Resurs Bank and ourselves.
     * @var array $apiData
     * @since 0.0.1.0
     */
    private array $apiData = [];
    /**
     * @var string $apiDataId
     * @since 0.0.1.0
     */
    private string $apiDataId = '';
    /**
     * @var PaymentMethod $paymentMethodInformation
     * @since 0.0.1.0
     */
    private PaymentMethod $paymentMethodInformation;

    /**
     * The iframe. Rendered once. When rendered, it won't be requested again.
     * @var string
     * @since 0.0.1.0
     */
    private string $rcoFrame;

    /**
     * The iframe container from Resurs Bank.
     * @var object
     * @since 0.0.1.0
     */
    private $rcoFrameData;

    /**
     * @var Generic $generic Generic library, mainly used for automatically handling templates.
     * @since 0.0.1.0
     */
    private $generic;

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
    private function initializePaymentMethod(?PaymentMethod $paymentMethod = null): void {
        // Make sure the cart can be reached if it is present.
        $this->cart = $woocommerce->cart ?? null;

        // @todo Switch this class to something else. This is mostly used for displaying templates in phtml-format.
        $this->generic = Data::getGenericClass();

        // Below is initial default preparations for the payment method, if we are unable to fetch it from the
        // real payment method information (via setPaymentMethodInformation). This id should preferably be
        // the UUID given by Resurs Bank (see below for that part).
        $this->id = 'resursbank_default';
        $this->method_title = 'Resurs Bank AB';
        $this->method_description = 'Resurs Bank Gateway';
        $this->title = 'Resurs Bank AB';

        // @todo Has fields should be false when implementing RCO.
        $this->has_fields = true;

        // This is where we initialize each payment method details, like if it is active, etc.
        // Most of the payment method information has to be initialized at or via the constructor due to
        // how WooCommerce start working with them as soon as they are loaded.
        $this->setPaymentMethodInformation(paymentMethod: $paymentMethod);

        $this->setFilters();
        $this->setActions();
    }

    /**
     * Initializer. It is not until we have payment method information we can start using this class for real.
     * @param PaymentMethod|null $paymentMethod
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     * @todo Eventually make this part compatible with RCO+ when time comes.
     */
    private function setPaymentMethodInformation(PaymentMethod $paymentMethod = null)
    {
        // Generic setup regardless of payment method.
        $this->setPaymentApiData();

        if ($paymentMethod instanceof PaymentMethod) {
            // Collect the entire payment method information.
            $this->paymentMethodInformation = $paymentMethod;
            $this->id = sprintf('%s_%s', Data::getPrefix(), $this->paymentMethodInformation->id);
            $this->title = $this->paymentMethodInformation->name ?? '';
            $this->method_description = '';

            // Separated this setting to make it easier to expand for future.
            $iconType = Data::getResursOption('payment_method_icons');
            $specificIcon = $this->getMethodIconUrl();
            $useSpecificRule = (
                $iconType === 'only_specifics' ||
                $iconType === 'specifics_and_resurs'
            );
            if ($iconType === 'woocommerce_icon') {
                // Default is set to the internal logo.
                $this->icon = Data::getImage('resurs-logo.png');
                if ($specificIcon !== null) {
                    $this->icon = $specificIcon;
                }
            } elseif ($useSpecificRule && $specificIcon !== null) {
                $this->icon = $specificIcon;
            }

            // Applicant post data should be the final request.
            $this->applicantPostData = $this->getApplicantPostData();
        }
    }

    /**
     * Generic setup regardless of payment method.
     * @return void
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setPaymentApiData(): void
    {
        $this->apiData['checkoutType'] = Data::getCheckoutType();
        if (!empty(Data::getPaymentMethodBySession())) {
            $this->apiData['paymentMethod'] = Data::getPaymentMethodBySession();
        }
        $this->apiDataId = sha1(uniqid('wc-api', true));
        $this->API = new ResursBankAPI();

    }

    /**
     * Decide how to use method icons in the checkout.
     * @return string
     * @since 0.0.1.0
     */
    private function getMethodIconUrl()
    {
        $return = null;
        // Data::getImage('resurs-logo.png')

        if (!empty($this->paymentMethodInformation)) {
            foreach (['type', 'specificType'] as $typeCheck) {
                if (($deprecatedIcon = $this->getMethodIconDeprecated($typeCheck))) {
                    $return = $deprecatedIcon;
                }
                if (($icon = $this->getIconByFilter())) {
                    $return = $icon;
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Payment method icon (deprecated filter).
     *
     * @param $typeCheck
     * @return string
     * @since 0.0.1.0
     */
    private function getMethodIconDeprecated($typeCheck): string
    {
        // @todo The typeCheck part is currently failing as MAPI has taken over the content.
        // @todo Since we're leaving v2.2 entirely, this filter may no longer be necessary.
        return !isset($this->paymentMethodInformation->{$typeCheck}) ? WordPress::applyFiltersDeprecated(
            sprintf(
                'woocommerce_resurs_bank_%s_checkout_icon',
                $this->paymentMethodInformation->{$typeCheck} ?? ''
            ),
            ''
        ) : '';
    }

    /**
     * @return mixed
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
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
            $saneRequest = Data::getSanitizedRequest($_REQUEST);
            foreach ($saneRequest as $requestKey => $requestValue) {
                // Note: When we pass through here, via the OrderHandler, this matching is not really
                // necessary.
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
     * @return string|string[]|null
     * @since 0.0.1.0
     */
    private function getRealMethodId()
    {
        return preg_replace(sprintf('/^%s_/', Data::getPrefix()), '', $this->id);
    }

    /**
     * Validate if payment is ready.
     *
     * @return bool
     * @since 0.0.1.0
     */
    private function isPaymentReady(): bool
    {
        return (isset($_REQUEST['payment_method'], $_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'checkout');
    }

    /**
     * @since 0.0.1.0
     */
    private function setFilters()
    {
        if (Data::isEnabled()) {
            add_filter('woocommerce_order_button_html', [$this, 'getOrderButtonHtml']);
            add_filter('woocommerce_checkout_fields', [$this, 'getCheckoutFields']);
            add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
            add_filter('woocommerce_get_terms_page_id', [$this, 'getTermsByRco'], 1);
            add_filter('woocommerce_order_get_payment_method_title', [$this, 'getPaymentMethodTitle'], 10, 2);
        }
    }

    /**
     * @since 0.0.1.0
     */
    private function setActions()
    {
        add_action('woocommerce_api_resursdefault', [$this, 'getApiRequest']);
        if (Data::isEnabled()) {
            add_action('wp_enqueue_scripts', [$this, 'getHeaderScripts'], 0);
            if (Data::getCheckoutType() === self::TYPE_RCO) {
                add_action(
                    sprintf('woocommerce_%s', Data::getResursOption('rco_iframe_position')),
                    [$this, 'getRcoIframe']
                );
            }
        }
    }

    /**
     * @return null
     * @since 0.0.1.0
     */
    public function getType()
    {
        return $this->getMethodInformation('type');
    }

    /**
     * @param $key
     * @return null
     * @since 0.0.1.0
     */
    private function getMethodInformation($key)
    {
        return $this->paymentMethodInformation->{$key} ?? null;
    }

    /**
     * @return null
     * @since 0.0.1.0
     */
    public function getSpecificType()
    {
        return $this->getMethodInformation('specificType');
    }

    /**
     * @return WC_Order
     * @since 0.0.1.0
     */
    public function getOrder(): WC_Order
    {
        return $this->order;
    }

    /**
     * @param $pageId
     * @return int|mixed
     * @since 0.0.1.0
     */
    public function getTermsByRco($pageId)
    {
        if (Data::getCheckoutType() === self::TYPE_RCO) {
            $pageId = 0;
        }
        return $pageId;
    }

    /**
     * Internal payment method title fetching, based on checkout type and the real payment method when in RCO.
     *
     * @return string
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     */
    public function get_title(): string
    {
        global $theorder;

        $return = parent::get_title();

        if (!empty($theorder)) {
            try {
                $internalPaymentTitle = $this->getPaymentMethodTitle($return, $theorder);
                if (!empty($internalPaymentTitle)) {
                    $return = $internalPaymentTitle;
                }
            } catch (Exception $e) {
                Data::writeLogException($e, __FUNCTION__);
            }
        }

        return $return;
    }

    /**
     * Get the correct (or incorrect) payment method title when order or payment method is pointing at RCO.
     *
     * @param $title
     * @param $order
     * @return mixed
     * @throws ResursException
     * @since 0.0.1.0
     */
    public function getPaymentMethodTitle($title, $order)
    {
        $orderMethodType = Data::getOrderMeta(
            'checkoutType',
            $order
        );
        if (empty($orderMethodType)) {
            $orderMethodType = Data::getCheckoutType();
        }

        // Use the current order checkout type, not the current configured checkout type.
        if ($orderMethodType === self::TYPE_RCO &&
            Data::getResursOption('rco_method_titling') !== 'default'
        ) {
            $this->order = $order;
            $internalTitle = Data::getOrderMeta('paymentMethod', $order);
            if (!empty($internalTitle)) {
                try {
                    // This that we want to use in higher PHP-versions but can not utilize until WooCommerce
                    // leaves the world of 7.0.
                    if (PHP_VERSION_ID >= 70300) {
                        /** @noinspection PhpStrictTypeCheckingInspection */
                        $paymentMethodDetails = json_decode(
                            Data::getResursOption('paymentMethods'),
                            JSON_THROW_ON_ERROR
                        );
                    } else {
                        // We want this as is.
                        /** @noinspection JsonEncodingApiUsageInspection */
                        $paymentMethodDetails = json_decode(Data::getResursOption('paymentMethods'));
                    }
                    if (is_array($paymentMethodDetails)) {
                        foreach ($paymentMethodDetails as $method) {
                            if (isset($method['id']) && $method['id'] === $internalTitle) {
                                $title = $method[Data::getResursOption('rco_method_titling')];
                            }
                        }
                    }
                } catch (Exception $e) {
                    Data::writeLogException($e, __FUNCTION__);
                }
            }
        }
        return $title;
    }

    /**
     * Enqueue scripts that is necessary for RCO (v2) to run properly.
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getHeaderScripts()
    {
        if (!(bool)WordPress::applyFiltersDeprecated('temporary_disable_checkout', null) &&
            Data::getCheckoutType() === self::TYPE_RCO
        ) {
            WooCommerce::setSessionValue(WooCommerce::$inCheckoutKey, true);

            $this->processRco();
        }
    }

    /**
     * Standard Resurs Checkout. Not interceptor ready.
     * @throws Exception
     * @since 0.0.1.0
     */
    private function processRco()
    {
        // setFraudFlags can not be set for this checkout type.
        $this->API->setCheckoutType(CheckoutType::RESURS_CHECKOUT);

        // Prevent recreation of an iframe, when it is not needed.
        // This could crash the order completion (at the successUrl).
        if (WooCommerce::getValidCart() && WooCommerce::getSessionValue(WooCommerce::$inCheckoutKey)) {
            $this->setOrderData();

            $paymentId = $this->getProperPaymentId();
            try {
                WooCommerce::applyMock('createIframeException');
                $this->rcoFrame = $this->API->getConnection()->createPayment($paymentId);
                $this->rcoFrameData = $this->API->getConnection()->getFullCheckoutResponse();
            } catch (Exception $e) {
                Data::writeLogEvent(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf(
                        __(
                            'An error (%s, code %s) occurred during the iframe creation. Retrying with a new ' .
                            'payment id.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $e->getMessage(),
                        $e->getCode()
                    )
                );
                $paymentId = $this->getProperPaymentId(true);
                try {
                    $this->rcoFrame = $this->API->getConnection()->createPayment($paymentId);
                    $this->rcoFrameData = $this->API->getConnection()->getFullCheckoutResponse();
                } catch (Exception $e) {
                    $this->rcoFrameData = new stdClass();
                    $this->rcoFrameData->script = '';
                    $this->rcoFrameData->exception = [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ];
                }
            } // End of exception.

            // Special method that is use when RCO is active.
            /** @noinspection PhpUndefinedMethodInspection */
            $this->rcoFrameData->legacy = $this->paymentMethodInformation->isLegacyIframe($this->rcoFrameData);

            // Since legacy is still a thing, we still need to fetch this variable, even if it is slightly isolated.
            WooCommerce::setSessionValue('rco_legacy', $this->rcoFrameData->legacy);

            $this->getProperRcoEnqueue();
        }
    }

    /**
     * Global order data handler. Things that happens to all flows.
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setOrderData(): self
    {
        Data::setDeveloperLog(__FUNCTION__, 'Start.');

        // Handle customer data from checkout only if this is not RCO (unless there is an order ready).
        // RCO handles them externally.
        if ($this->order && Data::getCheckoutType() !== self::TYPE_RCO) {
            $this
                ->setCustomer();
        }

        $this
            ->setCustomerId()
            ->setStoreId()
            ->getOrderLinesRco()
            ->setSigning();
        Data::setDeveloperLog(__FUNCTION__, 'Done.');

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setCustomer(): self
    {
        $governmentId = $this->getCustomerData('government_id');
        $customerType = Data::getCustomerType();
        Data::setDeveloperLog(__FUNCTION__, sprintf('setCountryByCountryCode:%s', $this->getCustomerData('country')));
        $this->API->getConnection()->setCountryByCountryCode($this->getCustomerData('country'));
        if ($governmentId) {
            Data::setDeveloperLog(__FUNCTION__, 'setBillingByGetAddress');
            // Prepare a billing address if government id is present.
            $this->API->getConnection()->setBillingByGetAddress($governmentId);
        } else {
            Data::setDeveloperLog(__FUNCTION__, 'setBillingAddress:WC_Order');
            $this->API->getConnection()->setBillingAddress(
                $this->getCustomerData('full_name'),
                $this->getCustomerData('first_name'),
                $this->getCustomerData('last_name'),
                $this->getCustomerData('address_1'),
                $this->getCustomerData('address_2'),
                $this->getCustomerData('city'),
                $this->getCustomerData('postcode'),
                $this->getCustomerData('country')
            );
        }

        if (isset($_REQUEST['ship_to_different_address'])) {
            Data::setDeveloperLog(__FUNCTION__, 'setDeliveryAddress:WC_Order:billing');
            $this->API->getConnection()->setDeliveryAddress(
                $this->getCustomerData('full_name', 'shipping'),
                $this->getCustomerData('first_name', 'shipping'),
                $this->getCustomerData('last_name', 'shipping'),
                $this->getCustomerData('address_1', 'shipping'),
                $this->getCustomerData('address_2', 'shipping'),
                $this->getCustomerData('city', 'shipping'),
                $this->getCustomerData('postcode', 'shipping'),
                $this->getCustomerData('country', 'shipping')
            );
        }

        $govIdData = $customerType === 'NATURAL' ? $this->getCustomerData('government_id') :
            $this->getCustomerData('applicant_government_id');

        Data::writeLogEvent(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s: govIdData %s',
                __FUNCTION__,
                $govIdData
            )
        );

        Data::setDeveloperLog(__FUNCTION__, 'setApiCustomer:$_POST');
        $this->API->getConnection()->setCustomer(
            $govIdData,
            $this->getCustomerData('phone'),
            $this->getCustomerData('mobile'),
            $this->getCustomerData('email'),
            $customerType,
            $this->getCustomerData('contact_government_id')
        );

        return $this;
    }

    /**
     * @param $key
     * @param $returnType
     * @return string
     * @since 0.0.1.0
     */
    private function getCustomerData($key, $returnType = null): string
    {
        // applicantPostData has been sanitized before reaching this point.
        $return = $this->applicantPostData[$key] ?? '';

        if ($key === 'mobile' && isset($return['phone']) && !$return) {
            $return = $return['phone'];
        }
        if ($key === 'phone' && isset($return['mobile']) && !$return) {
            $return = $return['phone'];
        }

        // If it's not in the post data, it could possibly be found in the order maintained from the order.
        $billingAddress = $this->order->get_address();
        $deliveryAddress = $this->order->get_address('shipping');

        if ((!$returnType || $returnType === 'billing') && !$return && isset($billingAddress[$key])) {
            $return = $billingAddress[$key];
        }
        if (($returnType === 'shipping' || $returnType === 'delivery') && !$return && isset($deliveryAddress[$key])) {
            $return = $deliveryAddress[$key];
        }

        if ($key === 'full_name') {
            // Full name is a merge from first and last name. It's made up but sometimes necessary.
            $return = sprintf('%s %s', $this->getCustomerData('first_name'), $this->getCustomerData('last_name'));
        }

        Data::writeLogEvent(Data::CAN_LOG_JUNK, sprintf('%s:%s,%s', __FUNCTION__, $key, $return));

        return (string)$return;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setSigning(): self
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

        // Running in RCO mode we most likely don't have any order to put metadata into, yet.
        if ($this->order && Data::getCheckoutType() !== self::TYPE_RCO) {
            // The data id is the hay value for finding prior orders on landing pages etc.
            $this->setOrderCheckoutMeta($this->order);
        }

        return $this;
    }

    /**
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
                __('Base URL for signing: %s', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
                $signingBaseUrl
            )
        );
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                __('Signing parameters for %s: %s', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
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
            $return = (new Strings())->base64urlEncode($return);
        }

        return (string)$return;
    }

    /**
     * Generic method to handle metadata depending on which direction the checkout is set into.
     * RCO needs this, but from the frontend calls. Simplified and hosted is the "regular" way,
     * so it's easier to handle.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setOrderCheckoutMeta($order)
    {
        Data::setOrderMeta($order, 'checkoutType', Data::getCheckoutType());
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
     */
    private function setStoreId(): ResursDefault
    {
        /** @noinspection PhpDeprecationInspection */
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

            /**
             * @todo set meta data when meta data is available in MAPI for externalCustomerId.
             * @todo For RCO it is still CustomerId.
             */
        }
        return $this;
    }

    /**
     * @return int
     * @since 0.0.1.0
     */
    private function getCustomerId(): int
    {
        $return = 0;
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
        } else {
            $current_user = get_currentuserinfo();
        }

        if (isset($current_user, $current_user->ID) && $current_user !== null) {
            $return = $current_user->ID;
        }
        // Created orders has higher priority since this id might have been created during order processing
        if (!empty($this->order) && method_exists($this->order, 'get_user_id')) {
            $orderUserId = $this->order->get_user_id();
            if ($orderUserId) {
                $return = $orderUserId;
            }
        }

        return $return;
    }

    /**
     * Get payment method from ecom data.
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
     * Find out which order reference that should be used in RCO.
     *
     * @param bool $forceNew
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     * @todo This is more likely to return a string and still includes a lot of old stuff covering ecom1.
     * @todo That must be fixed.
     */
    private function getProperPaymentId(bool $forceNew = false): static
    {
        $paymentId = $this->API->getConnection()->getPreferredPaymentId();

        if ((bool)$forceNew) {
            $lastRcoOrderId = $this->API->getConnection()->getPreferredPaymentId(25, '', true, true);
        } else {
            $lastRcoOrderId = WooCommerce::getSessionValue('rco_order_id');
        }

        if (!$forceNew && $this->getRcoPaymentIdTooOld()) {
            $lastRcoOrderId = null;
            WooCommerce::setSessionValue('rco_order_id', $lastRcoOrderId);
        }

        // Store the payment id for later use.
        if ($lastRcoOrderId !== $paymentId && !empty($lastRcoOrderId)) {
            $paymentId = $lastRcoOrderId;
            $this->API->getConnection()->setPreferredId($paymentId);

            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __(
                        'Reusing preferred payment id "%s" for customer, age %d seconds old.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $lastRcoOrderId,
                    $this->getRcoPaymentIdTooOld(true)
                )
            );
        } else {
            WooCommerce::setSessionValue('rco_order_id_age', time());
            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __(
                        'Preferred payment id set to "%s" for customer.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $paymentId
                )
            );
        }

        WooCommerce::setSessionValue('rco_order_id', $paymentId);

        return $paymentId;
    }

    /**
     * Find out if payment id is too old to be kept in session.
     * @param bool $returnAgeValue
     * @return bool|int
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @noinspection SpellCheckingInspection
     */
    private function getRcoPaymentIdTooOld($returnAgeValue = false)
    {
        $rcoOrderIdAge = (int)WooCommerce::getSessionValue('rco_order_id_age');
        $calculateAge = time() - $rcoOrderIdAge;
        $optionMaxAge = (int)Data::getResursOption('rco_paymentid_age');
        $rcoOrderAgeLimit = (int)WordPress::applyFilters(
            'getRcoOrderAgeLimit',
            $optionMaxAge > 0 ? $optionMaxAge : 3600
        );

        return !$returnAgeValue ? ($calculateAge > $rcoOrderAgeLimit) : $calculateAge;
    }

    /**
     * Prepare for RCO depending on what happened. We need to make exceptions available in frontend from this point.
     *
     * @throws Exception
     * @since 1.0.0
     */
    private function getProperRcoEnqueue()
    {
        $urlList = isset($this->rcoFrameData->script) ?
            (new Domain())->getUrlsFromHtml($this->rcoFrameData->script) : [];

        if (isset($this->rcoFrameData->script)) {
            if (!empty($this->rcoFrameData->script) && count($urlList)) {
                $this->rcoFrameData->originHostName = $this
                    ->API
                    ->getConnection()
                    ->getIframeOrigin($this->rcoFrameData->baseUrl);
                wp_enqueue_script(
                    'trbwc_rco',
                    array_pop($urlList),
                    ['jquery']
                );
                unset($this->rcoFrameData->customer);
                wp_localize_script(
                    'trbwc_rco',
                    'trbwc_rco',
                    (array)$this->rcoFrameData
                );
            } else {
                wp_enqueue_script(
                    'trbwc_rco',
                    Data::getGatewayUrl() . '/js/trbwc_rco.js',
                    ['jquery']
                );
                wp_localize_script(
                    'trbwc_rco',
                    'trbwc_rco',
                    (array)$this->rcoFrameData
                );
            }
        }
    }

    /**
     * @param string $rowType
     * @param WC_Product $productData
     * @param array $item
     * @return ResursDefault
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setOrderRow($rowType, $productData, $item): ResursDefault
    {
        $this->API->getConnection()->addOrderLine(
            $this->getFromProduct('artNo', $productData),
            $this->getFromProduct('title', $productData),
            $this->getFromProduct('unitAmountWithoutVat', $productData),
            $this->getFromProduct('vatPct', $productData),
            $this->getFromProduct('unit', $productData),
            $rowType,
            $item['quantity']
        );

        return $this;
    }

    /**
     * @param OrderLineType $orderLineType
     * @param WC_Product $productData
     * @param array $wcProductItem Product item details from WooCommerce, contains the extended data that can't be found in WC_Product.
     * @return OrderLine
     * @throws IllegalValueException
     * @throws Exception
     */
    public function getMapiOrderProductRow(OrderLineType $orderLineType, WC_Product $productData, array $wcProductItem): OrderLine
    {
        return new OrderLine(
            description: $this->getFromProduct(getValueType: 'title', productObject: $productData, wcProductItemData: $wcProductItem),
            reference: $this->getFromProduct(getValueType: 'reference', productObject: $productData, wcProductItemData: $wcProductItem),
            quantityUnit: $this->getFromProduct(
                getValueType: 'quantityUnit',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            quantity: $wcProductItem['quantity'],
            vatRate: $this->getFromProduct(getValueType: 'vatRate', productObject: $productData, wcProductItemData: $wcProductItem),
            unitAmountIncludingVat: $this->getFromProduct(
                getValueType: 'unitAmountWithVat',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            totalAmountIncludingVat: $this->getFromProduct(
                getValueType: 'totalAmountWithVat',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            totalVatAmount: $this->getFromProduct(
                getValueType: 'totalVatAmount',
                productObject: $productData,
                wcProductItemData: $wcProductItem
            ),
            type: $orderLineType
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
     * @throws IllegalValueException
     */
    public function getMapiCustomOrderLine(
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
            description: $description,
            reference: $reference,
            quantityUnit: Translator::translate('default-quantity-unit'),
            quantity: (float)$quantity,
            vatRate: $vatRate,
            unitAmountIncludingVat: $unitAmountIncludingVat,
            totalAmountIncludingVat: $totalAmountIncludingVat,
            totalVatAmount: $totalVatAmount,
            type: $orderLineType
        );
    }

    /**
     * @param string $getValueType
     * @param WC_Product $productObject
     * @param array $wcProductItemData
     * @return float|int|string
     * @since 0.0.1.0
     */
    protected function getFromProduct(string $getValueType, WC_Product $productObject, array $wcProductItemData = []): float|int|string
    {
        $return = '';

        // $wcProductItemData always returns same information for at product. The data here can always be expected
        // based on the content from WC_Order_Item (see $extra_data in WC_Order_Item).

        // If you see multiple cases, this is mostly used for the backward compatibility of the old RCO API.
        switch ($getValueType) {
            case 'artNo':
            case 'reference':
                $return = $this->getProperArticleNumber($productObject);
                break;
            case 'description':
            case 'title':
                $return = !empty($useTitle = $productObject->get_title()) ? $useTitle : __(
                    'Article description is missing.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                );
                break;
            case 'unitAmountWithVat':
            case 'unitAmountIncludingVat':
                $return = wc_get_price_including_tax($productObject);
                break;
            case 'totalAmountWithVat':
            case 'totalAmountIncludingVat':
                $return = wc_get_price_including_tax($productObject, ['qty' => $wcProductItemData['quantity']]);
                break;
            case 'unitAmountWithoutVat':
                // Special reflection of what Resurs Bank wants.
                $return = wc_get_price_excluding_tax($productObject);
                break;
            case 'totalVatAmount':
                $return = wc_get_price_including_tax($productObject, ['qty' => $wcProductItemData['quantity']]) - wc_get_price_excluding_tax(
                        $productObject,
                        ['qty' => $wcProductItemData['quantity']]
                    );
                break;
            case 'vatPct':
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
    private function getProperArticleNumber($product)
    {
        return WooCommerce::getProperArticleNumber($product);
    }

    /**
     * @param WC_Product $product
     * @return float|int
     * @since 0.0.1.0
     */
    private function getProductVat($product)
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
            // @todo Return false if woocommerce internally has this gateway disabled.
            // @todo Also eventually check if the payment method is enabled in the admin (if want to go that way).
        }
        $customerType = Data::getCustomerType();

        Data::getPaymentMethodBySession();

        // If this feature is not missing the method, we now know that there is chance that we're
        // located in a checkout. We will in this moment run through the min-max amount that resides
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
     * @param int $minLimit
     * @return float
     * @link https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/42
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    private function getRealMin($minLimit): float
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
     * @param int $maxLimit
     * @return float
     * @link https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/42
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    private function getRealMax($maxLimit): float
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
     * How to handle the submit order button. For future RCO.
     *
     * @param $classButtonHtml
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getOrderButtonHtml($classButtonHtml): string
    {
        $return = '';

        $woocommerceMethodName = Data::getMethodFromFragmentOrSession();
        $checkoutType = Data::getCheckoutType();

        if (($checkoutType === self::TYPE_RCO && !preg_match('/RESURS_CHECKOUT$/', $woocommerceMethodName)) ||
            $checkoutType !== self::TYPE_RCO
        ) {
            $return = $classButtonHtml;
        } elseif (WooCommerce::getValidCart() && (float)WC()->cart->total === 0.00) {
            $return = $classButtonHtml;
        }

        // Cast it securely.
        return (string)$return;
    }

    /**
     * How to handle checkout fields. For future RCO.
     *
     * @param $fields
     * @return mixed
     * @since 0.0.1.0
     */
    public function getCheckoutFields($fields)
    {
        return $fields;
    }

    /**
     * @since 0.0.1.0
     */
    public function admin_options()
    {
        $_REQUEST['tab'] = Data::getPrefix('admin');
        $url = admin_url('admin.php');
        $url = add_query_arg('page', Data::getRequest('page'), $url);
        $url = add_query_arg('tab', Data::getRequest('tab'), $url);
        wp_safe_redirect($url);
        die('Deprecated space');
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    public function payment_fields()
    {
        $fieldHtml = null;

        if (Data::getCheckoutType() === self::TYPE_RCO) {
            return;
        }

        // If not here, no fields are required.
        /** @noinspection PhpUndefinedFieldInspection */
        $requiredFields = FormFields::getSpecificTypeFields(
            $this->paymentMethodInformation->type,
            Data::getCustomerType()
        );

        if (count($requiredFields)) {
            $getAddressVisible = Data::canUseGetAddressForm();
            foreach ($requiredFields as $fieldName) {
                $fieldValue = null;
                $displayField = $this->getDisplayableField($fieldName);
                $alwaysShowApplicantFields = Data::getResursOption('streamline_payment_fields');
                switch ($fieldName) {
                    case 'government_id':
                        $fieldValue = WooCommerce::getSessionValue('identification');
                        if (!$getAddressVisible) {
                            $displayField = true;
                        } elseif (!$alwaysShowApplicantFields) {
                            $displayField = false;
                        }
                        $isInternal = self::isInternalMethod($this->paymentMethodInformation);
                        if (!$isInternal && $displayField) {
                            $displayField = false;
                            // External payment methods does not require the govt. id.
                            $alwaysShowApplicantFields = false;
                        }

                        // As we don't know if this field is filled in when we enter the page, we prefer to
                        // properly show it, if it has been seen as empty when entering the checkout. Letting
                        // the ecom-helper decide if the method is internal, this is easier to follow.
                        if ($isInternal && !$displayField && empty($fieldValue)) {
                            $displayField = true;
                        }
                        break;
                    default:
                        if (!$getAddressVisible || $alwaysShowApplicantFields) {
                            $displayField = true;
                        }
                }
                /** @noinspection SpellCheckingInspection */
                $fieldHtml .= $this->generic->getTemplate('checkout_paymentfield.phtml', [
                    'displayMode' => $displayField ? '' : 'none',
                    'methodId' => $this->paymentMethodInformation->id ?? '?',
                    'fieldSize' => WordPress::applyFilters('getPaymentFieldSize', 24, $fieldName),
                    'alwaysShowApplicantFields' => $alwaysShowApplicantFields,
                    'fieldLabel' => FormFields::getFieldString($fieldName),
                    'fieldName' => sprintf(
                        '%s_%s_%s',
                        Data::getPrefix(),
                        $fieldName,
                        $this->paymentMethodInformation->id
                    ),
                    'fieldValue' => $fieldValue,
                ]);
            }

            /** @noinspection SpellCheckingInspection */
            $fieldHtml .= $this->generic->getTemplate('checkout_paymentfield_after.phtml', [
                'method' => $this->paymentMethodInformation,
                'total' => $this->cart->total ?? 0,
                'customDescription' => WooCommerce::getCustomDescription($this->paymentMethodInformation->id),
            ]);

            // Considering this place as a safe place to apply display in styles.
            add_filter('safe_style_css', function ($styles) {
                $styles[] = 'display';
                return $styles;
            });

            echo Data::getEscapedHtml($fieldHtml);
        }
    }

    /**
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
     * @param $order_id
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public function process_payment($order_id): array
    {
        $order = new WC_Order($order_id);
        $this->order = $order;
        // Return template.
        $return = [
            'ajax_success' => true,
            'result' => 'failure',
            'redirect' => $this->getReturnUrl($order),
        ];

        if (empty(Data::getOrderMeta($order_id, 'checkoutType')) &&
            WooCommerce::getSessionValue('resursCheckoutType') &&
            Data::getCheckoutType() === self::TYPE_RCO
        ) {
            // Updating missing order information on fly during RCO session as it is missing when we're
            // still in the API call. This data becomes reachable as soon as WooCommerce gives us this
            // through here.
            $sessionCheckoutType = WooCommerce::getSessionValue('resursCheckoutType');
            Data::writeLogNotice(
                __(
                    sprintf(
                        'Checkout type in order meta is empty, but found in session as %s. This usually ' .
                        'occurs in RCO mode. Order meta data will update.',
                        $sessionCheckoutType
                    )
                )
            );
            $this->setOrderCheckoutMeta($order_id);
            WooCommerce::setOrderNote(
                $order_id,
                sprintf(
                    __('Order process initialized by customer with checkout %s.'),
                    Data::getCheckoutType()
                )
            );
            // Used with RCO. Need to be here, at least for a while longer.
            $order->set_payment_method(Data::getPaymentMethodBySession());
            //$this->setProperPaymentReference($order);
            // From here, we can handle the order at regular basis.
        }

        if (empty(Data::getOrderMeta('paymentMethodInformation', $order))) {
            $paymentMethodInformation = Data::getPaymentMethodById(Data::getPaymentMethodBySession());
            if (is_object($paymentMethodInformation)) {
                Data::setOrderMeta($order, 'paymentMethodInformation', json_encode($paymentMethodInformation));
            }
        }

        // We will most likely land here if order_awaiting_payment was null at first init and checkout was of type RCO.
        // As RCO is very much backwards handled, we have to update meta data on order succession so that
        // we can match the correct order on successUrl later on.
        $metaCheckoutType = Data::getOrderMeta('checkoutType', $order_id);
        if (empty($metaCheckoutType)) {
            $this->setOrderCheckoutMeta($order_id);
            $paymentMethodBySession = Data::getPaymentMethodBySession();
            if ($metaCheckoutType === self::TYPE_RCO && !empty($paymentMethodBySession)) {
                $order->set_payment_method(Data::getPaymentMethodBySession());
            }
        }

        $this->preProcessOrder($order);
        if (Data::getCheckoutType() !== self::TYPE_RCO) {
            $return = $this->processResursOrder($order);
        } elseif (Data::getCheckoutType() === self::TYPE_RCO) {
            // Rules applicable on RCO only.
            $return['result'] = 'success';
            $return['total'] = (float)$this->cart->total;
            $return['redirect'] = $this->get_return_url($order);
        }

        return $return;
    }

    /**
     * @param $order
     * @param string $result
     * @param null $resursReturnUrl
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private function getReturnUrl($order, $result = 'failure', $resursReturnUrl = null)
    {
        $returnUrl = $this->get_return_url($order);
        if (empty($resursReturnUrl)) {
            $returnUrl = $resursReturnUrl;
        }

        return $result === 'success' ? $returnUrl : html_entity_decode($order->get_cancel_order_url());
    }

    /**
     * Handler for updatePaymentReference.
     *
     * @param $order
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     */
    private function setProperPaymentReference($order)
    {
        $referenceType = Data::getResursOption('order_id_type');

        switch ($referenceType) {
            case 'reserved-for-future-use':
                break;
            case 'postid':
                $idBeforeChange = $this->getPaymentIdBySession();
                try {
                    WooCommerce::applyMock('updatePaymentReferenceFailure');
                    $newPaymentId = $this->getOrderReference();
                    if ($idBeforeChange !== $newPaymentId) {
                        ResursBankAPI::getResurs()->updatePaymentReference($idBeforeChange, $newPaymentId);
                        Data::setOrderMeta(
                            $order,
                            'initialUpdatePaymentReference',
                            sprintf('%s:%s', $idBeforeChange, $newPaymentId)
                        );
                        Data::setOrderMeta($order, 'updatePaymentReference', date('Y-m-d H:i:s', time()));
                        WooCommerce::setOrderNote(
                            $order,
                            sprintf(
                                __(
                                    'Order reference updated (updatePaymentReference) with no errors.',
                                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                                )
                            )
                        );
                        // If order id is updated properly, our session based order id should also be updated in case
                        // of reloads.
                        WooCommerce::setSessionValue('rco_order_id', $newPaymentId);
                    } else {
                        WooCommerce::setOrderNote(
                            $order,
                            sprintf(
                                __(
                                    'Order reference not updated: Reference is already updated.',
                                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                                )
                            )
                        );
                    }
                } catch (Exception $e) {
                    Data::writeLogNotice(
                        sprintf(
                            'updatePaymentReference failed: %s (code %s).',
                            $e->getMessage(),
                            $e->getCode()
                        )
                    );
                    WooCommerce::setOrderNote(
                        $order,
                        sprintf(
                            __(
                                'updatePaymentReference failure (code %s): %s.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            ),
                            $e->getCode(),
                            $e->getMessage()
                        )
                    );
                    Data::setOrderMeta(
                        $order,
                        'updatePaymentReference',
                        sprintf('%s (%s).', $e->getMessage(), $e->getCode())
                    );
                }
                break;
            default:
                // Do nothing and live your life if default is used.
        }
    }

    /**
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
     * @param WC_Order $order
     * @return array
     * @throws Exception
     */
    private function processResursOrder(WC_Order $order): array
    {
        if (Data::getCheckoutType() !== ResursDefault::TYPE_RCO) {
            $return = $this->processSimplified();
        } else {
            // @todo Handle RCO.
            //$return = $this->processRco($order);
        }

        if (!isset($return['result'])) {
            $return = [
                'result' => 'failure',
                'redirect' => $this->getReturnUrl($order, 'failure')
            ];
        }

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection IssetConstructsCanBeMergedInspection
     */
    public function getApiRequest()
    {
        $finalRedirectUrl = wc_get_cart_url();

        if (isset($_REQUEST['c'])) {
            WooCommerce::getHandledCallback();
            exit;
        }

        if (isset($_REQUEST['apiData'])) {
            $this->getApiByRco();
            $this->setApiData(json_decode(
                (new Strings())->base64urlDecode(Data::getRequest('apiData')),
                true
            ));

            $this->order = $this->getCurrentOrder();
            $this->apiData['isReturningCustomer'] = false;

            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __('API data request: %s.', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
                    $this->apiData['isReturningCustomer'] ?
                        __(
                            'Customer returned from Resurs Bank. WooCommerce order validation in progress.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ) :
                        __(
                            'wc_order_id + preferred_id not present',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        )
                )
            );
            $this->wcOrderData = Data::getOrderInfo($this->order);
            $finalSigningResponse = $this->setFinalSigning();

            if ($this->getApiValue('wc_order_id') && $this->getApiValue('preferred_id')) {
                $this->apiData['isReturningCustomer'] = true;

                if ($this->order instanceof WC_Order) {
                    $orderHandler = new OrderHandler();
                    $orderHandler->getCustomerRealAddress($this->order);
                }
            }

            if ($this->isSuccess($finalSigningResponse)) {
                // Inspections reacted on this if conditions and suggested merging the isset. That, we won't do.
                if (isset($finalSigningResponse) &&
                    is_array($finalSigningResponse) &&
                    isset($finalSigningResponse['redirect'])
                ) {
                    $finalRedirectUrl = $finalSigningResponse['redirect'];
                }
                WooCommerce::setSessionValue('rco_order_id', null);
                WooCommerce::setSessionValue('customerCartTotal', null);

                switch ($this->getCheckoutType()) {
                    case self::TYPE_SIMPLIFIED:
                        // When someone returns with a successful call.
                        $signingRedirectTime = Data::getOrderMeta('signingRedirectTime', $this->wcOrderData);
                        if ($signingRedirectTime) {
                            $finalRedirectUrl = $this->get_return_url($this->order);
                        } elseif ($finalSigningResponse['result'] === 'success') {
                            Data::writeLogInfo('Final Signing Response was reported success, but redirect triggered too fast.');
                            // Race conditions could happen when redirects are too fast.
                            $finalRedirectUrl = $this->get_return_url($this->order);
                        }
                        break;
                    case self::TYPE_RCO:
                        // Logging for specific flow.
                        if ($this->getCheckoutType() === self::TYPE_RCO) {
                            Data::writeLogEvent(
                                Data::CAN_LOG_ORDER_EVENTS,
                                __(
                                    'Session value rco_order_id has been reset after successful ' .
                                    'return to landing page.',
                                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                        }
                        $finalRedirectUrl = $this->get_return_url($this->order);
                        break;
                    default:
                }
            } else {
                // Landing here is complex, but this part of the method is based on failures.
                $signing = false;       // Initially, we presume no signing was in action.
                if (Data::getOrderMeta('signingRedirectTime', $this->wcOrderData)) {
                    // If we however find the order flagged with signing requirement metas, we presume
                    // customer aborted payment or failed the signing.
                    $signing = true;
                }
                // Now, we should push out a notice to the customer that the order is about to get annulled
                // in WooCommerce. If there is signs of signing, the message will look a bit different.
                $setCustomerNotice = $this->getCustomerAfterSigningNotices($signing);
                // Now that we have the customer notice, we'll create a similar for the merchant the same way.
                $cancelNote = $this->getCancelNotice($signing);
                // For the sake of logging, we'll also add that note to the logged to keep them collected for
                // debugging.
                Data::writeLogEvent(Data::CAN_LOG_ORDER_EVENTS, $cancelNote);
                // Now that we have all necessary data, we'll cancelling the order.
                $this->updateOrderStatus(
                    self::STATUS_CANCELLED,
                    $cancelNote
                );
                // And then we prepare WooCommerce to show this visually.
                wc_add_notice($setCustomerNotice);
            }
        }

        Data::writeLogInfo(
            sprintf(
                __(
                    'Finishing. Ready to redirect customer. Using URL %s',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                print_r($finalRedirectUrl, true)
            )
        );

        wp_safe_redirect($finalRedirectUrl);
        die;
    }

    /**
     * If API request is based on RCO, we can not entirely trust the landing page API content.
     * In that case we have to re merge some data from the session instead as the "signing success url"
     * is empty when the iframe is rendered.
     *
     * @return $this
     * @throws JsonException
     * @since 0.0.1.0
     */
    private function getApiByRco(): self
    {
        $baseHandler = new Strings();
        $apiRequestContent = json_decode($baseHandler->base64urlDecode(Data::getRequest('apiData')), true);

        if ($apiRequestContent['checkoutType'] === self::TYPE_RCO) {
            $requestSession = [
                'preferred_id' => 'rco_order_id',
                'wc_order_id' => 'order_awaiting_payment',
            ];
            $apiRequestContent = $this->getSessionApiData($apiRequestContent, $requestSession);
            // Re-encode the data again.
            $_REQUEST['apiData'] = $baseHandler->base64urlEncode(json_encode($apiRequestContent, JSON_THROW_ON_ERROR));
        }
        return $this;
    }

    /**
     * @param $apiRequestContent
     * @param $requestArray
     * @return mixed
     * @since 0.0.1.0
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
        $awaitingOrderId = WooCommerce::getSessionValue('order_awaiting_payment');
        if ($awaitingOrderId && !$return->get_id()) {
            $return = new WC_Order($awaitingOrderId);
        }

        return $return;
    }

    /**
     * @param $key
     * @return mixed|string
     * @since 0.0.1.0
     */
    public function getApiValue($key)
    {
        // If key is empty or not found, return the whole set.
        return (string)isset($this->apiData[$key]) ? $this->apiData[$key] : $this->apiData;
    }

    /**
     * Final signing: Checks and update order if signing was required initially. Let it through on hosted but
     * keep logging the details.
     *
     * @return bool|array
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setFinalSigning(): bool|array
    {
        $return = false;
        try {
            if (!($lastExceptionCode = Data::getOrderMeta('bookSignedPaymentExceptionCode', $this->order))) {
                $bookSignedOrderReference = Data::getOrderMeta('resursReference', $this->wcOrderData);
                // This part of the plugin is intended to save performance by not running bookSignedPayment if
                // callbacks are reaching the platform before the customer landing.
                if (Data::getOrderMeta('resursCallbackReceived', $this->order)) {
                    $return = [
                        'result' => 'success',
                        'redirect' => $this->get_return_url($this->order),
                    ];
                    $quickLandResponse = sprintf(
                        __(
                            'Callback retrieved for order %s before customer reached the order confirmation page.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $this->order->get_id()
                    );
                    Data::writeLogEvent(
                        LogLevel::INFO,
                        $quickLandResponse
                    );
                    $this->order->add_order_note(
                        $quickLandResponse
                    );
                } else {
                    $this->setFinalSigningNotes($bookSignedOrderReference);

                    // Signing is only necessary for simplified flow.
                    if ($this->getCheckoutType() === self::TYPE_SIMPLIFIED) {
                        // @todo SOAP returned an array with a status after bookSignedPayment, but MAPI
                        // @todo differs slightly in this process. This is where we preferably handle signings which
                        // @todo aso means that getResultByPaymentStatus can be replaced.
                        /*$this->paymentResponse = $this->API->getConnection()->bookSignedPayment(
                            $bookSignedOrderReference
                        );
                        $return = $this->getResultByPaymentStatus();*/
                    }
                }
            } else {
                $this->setFinalSigningProblemNotes($lastExceptionCode);
            }
        } catch (Exception $bookSignedException) {
            Data::writeLogException($bookSignedException, __FUNCTION__);
            $this->setFinalSigningExceptionNotes($bookSignedException);
        }

        return $return;
    }

    /**
     * Get checkout type by API call.
     *
     * @return string
     * @since 0.0.1.0
     */
    private function getCheckoutType(): string
    {
        return (string)$this->getApiValue('checkoutType');
    }

    /**
     * @param $bookSignedOrderReference
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setFinalSigningNotes($bookSignedOrderReference)
    {
        $customerSignedMessage = sprintf(
            __(
                'Customer returned from external source. Order statuses for order %s that is not yet handled, ' .
                'are queued.',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ),
            $bookSignedOrderReference
        );
        Data::writeLogEvent(
            Data::CAN_LOG_ORDER_EVENTS,
            $customerSignedMessage
        );
        WooCommerce::setOrderNote(
            $this->order,
            $customerSignedMessage
        );
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getResultByPaymentStatus(): array
    {
        $bookPaymentStatus = $this->getBookPaymentStatus();
        Data::writeLogEvent(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s bookPaymentStatus order %s:%s',
                __FUNCTION__,
                $this->order->get_id(),
                $bookPaymentStatus
            )
        );

        // Always add status update to queue instead of setting it instantly. Further below we will
        // only add an order note, so the queue-system will handle the proper status (this also prevents
        // race conditions between customer and callback).
        OrderStatus::setOrderStatusByQueue($this->order);

        switch ($bookPaymentStatus) {
            case 'FINALIZED':
                $this->setSigningMarked();
                $this->order->add_order_note(
                    WooCommerce::getOrderNotePrefixed(
                        __(
                            'Order is reportedly debited and completed. ' .
                            'Status update request is queued.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        )
                    )
                );
                $return = $this->getResult('success');
                break;
            case 'BOOKED':
                $this->setSigningMarked();
                $this->order->add_order_note(
                    __(
                        'Order is booked and ready to handle. Status update request is queued.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
                $return = $this->getResult('success', $this->getApiValue('successUrl'));
                break;
            case 'FROZEN':
                $this->setSigningMarked();
                $this->order->add_order_note(
                    __(
                        'Order is frozen and waiting for manual inspection. Status update request is queued.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
                $return = $this->getResult('success', $this->getApiValue('successUrl'));
                break;
            case 'SIGNING':
                Data::setOrderMeta($this->order, 'signingRedirectTime', date('Y-m-d H:i:s', time()));
                // self::STATUS_SIGNING = on-hold
                $this->order->add_order_note(
                    __(
                        'Resurs Bank requires external handling/signing on this order. Customer redirected.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
                $return = $this->getResult('success', $this->getBookSigningUrl());
                break;
            default:
                // Everything received without a proper status usually fails.
                // This includes DENIED (and FAILED if it still exists).
                WooCommerce::setOrderNote(
                    $this->order,
                    sprintf(
                        __(
                            'Credit application could not be approved (status received: %s). ' .
                            'Customer was notified to choose another payment method.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $this->getBookPaymentStatus()
                    )
                );
                wc_add_notice(
                    __(
                        'The payment can not complete. Please choose another payment method.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'error'
                );
                $return = $this->getResult('failed');
                break;
        }
        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private function getBookPaymentStatus(): string
    {
        return $this->getPaymentResponse('bookPaymentStatus');
    }

    /**
     * @param $key
     * @return mixed
     * @since 0.0.1.0
     */
    private function getPaymentResponse($key)
    {
        return $this->paymentResponse->$key ?? '';
    }

    /**
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setSigningMarked()
    {
        $return = false;
        if (Data::getOrderMeta('signingRedirectTime', $this->order) &&
            Data::getOrderMeta('bookPaymentStatus', $this->order) &&
            empty(Data::getOrderMeta('signingOk', $this->order))
        ) {
            $return = Data::setOrderMeta($this->order, 'signingOk', date('Y-m-d H:i:s', time()));
            Data::setOrderMeta(
                $this->order,
                'signingConfirmed',
                sprintf(
                    'CustomerLanding:%s',
                    date('Y-m-d H:i:s', time())
                )
            );
        }
        return $return;
    }

    /**
     * @param $woocommerceStatus
     * @param $statusNotification
     * @throws Exception
     * @since 0.0.1.0
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
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
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
     * @param $status
     * @param $redirect
     * @return array
     * @since 0.0.1.0
     */
    private function getResult($status, $redirect = null): array
    {
        if (empty($redirect)) {
            $redirect = $status === 'failed' ?
                $this->order->get_cancel_order_url() : $this->get_return_url($this->order);
        }
        $redirectString = is_string($redirect) ? $redirect : print_r($redirect, true);
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                'Result: %s, Redirect to %s.',
                $status,
                $redirectString
            )
        );

        $successRedirect = $this->get_return_url($this->order);
        // If we get an array instead of a string here, then we should consider the handling
        // currently not finally processed. This status should be forwarded back as is, unless
        // the success is true. Then we give the service a proper redirection url instead.
        if (is_array($redirect) && isset($redirect['success']) && $redirect['success']) {
            $redirect = $successRedirect;
        }

        return [
            'result' => $status,
            'redirect' => $redirect,
        ];
    }

    /**
     * @return mixed|string
     * @since 0.0.1.0
     */
    private function getBookSigningUrl()
    {
        return (string)$this->getPaymentResponse('signingUrl');
    }

    /**
     * @param $lastExceptionCode
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setFinalSigningProblemNotes($lastExceptionCode)
    {
        WooCommerce::setOrderNote(
            $this->order,
            sprintf(
                __(
                    'Booking this signed payment has been running multiple times but failed ' .
                    'with exception %s.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                $lastExceptionCode
            )
        );
        Data::writeLogNotice(
            sprintf(
                __(
                    'Tried to book signed payment but skipped: This has happened before and an ' .
                    'exception with code %d occurred that time.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                $lastExceptionCode
            )
        );
    }

    /**
     * @param $bookSignedException
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setFinalSigningExceptionNotes($bookSignedException)
    {
        Data::writeLogException($bookSignedException, __FUNCTION__);
        Data::setOrderMeta(
            $this->order,
            'bookSignedPaymentExceptionCode',
            $bookSignedException->getCode()
        );
        Data::setOrderMeta(
            $this->order,
            'bookSignedPaymentExceptionMessage',
            $bookSignedException->getMessage()
        );
    }

    /**
     * @return array|mixed|string
     * @since 0.0.1.0
     */
    private function isSuccess($finalSigningResponse)
    {
        return isset($finalSigningResponse['result']) &&
        $finalSigningResponse['result'] === 'failed' ? false : $this->getApiValue('success');
    }

    /**
     * @param $signing
     * @return string
     * @since 0.0.1.0
     */
    private function getCustomerAfterSigningNotices($signing): string
    {
        $return = __(
            'Could not complete your order. Please contact support for more information.',
            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
        );

        if ($signing) {
            $return = (string)__(
                'Could not complete order due to signing problems.',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            );
        }

        return (string)$return;
    }

    /**
     * @param $signing
     * @return string
     * @since 0.0.1.0
     */
    private function getCancelNotice($signing): string
    {
        return sprintf(
            __(
                'Customer returned via urlType "%s" - failed or cancelled payment (signing required: %s).',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ),
            $this->getUrlType(),
            $signing ? __('Yes', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce') : __(
                'No',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            )
        );
    }

    /**
     * @return array|mixed|string
     * @since 0.0.1.0
     */
    private function getUrlType()
    {
        return $this->getApiValue('urlType');
    }

    /**
     * @since 0.0.1.0
     */
    public function getRcoIframe()
    {
        echo Data::getEscapedHtml(
            WordPress::applyFilters(
                'getRcoContainerHtml',
                sprintf(
                    '<div id="resursbank_rco_container"></div>'
                )
            )
        );
    }

    /**
     * @return OrderLineCollection
     * @throws IllegalTypeException
     */
    private function getOrderLinesMapi(): OrderLineCollection
    {
        if (WooCommerce::getValidCart()) {
            $orderHandler = new OrderHandler();
            return $orderHandler->getPreparedMapiOrderLines();
        }

        // todo: Translate this via ecom2.
        throw new RuntimeException(
            __('Cart is empty!')
        );
    }

    /**
     * @return ResursDefault
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    private function getOrderLinesRco(): ResursDefault
    {
        // TODO: Leaving this as is until we work with RCO. Must be fixed, but the orderLines are different to MAPI.
        if (WooCommerce::getValidCart()) {
            // This is the first point we store the cart total for the current session.
            WooCommerce::setSessionValue('customerCartTotal', WC()->cart->total);
            //$orderHandler = new OrderHandler();
            //$orderHandler->setCart($this->cart);
            //$orderHandler->setApi($this->API);
            //$orderHandler->setPreparedOrderLines();
            //$this->API = $orderHandler->getApi();

            Data::writeLogEvent(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    '%s',
                    __FUNCTION__
                )
            );
        } else {
            Data::writeLogError(
                sprintf(
                    __(
                        '%s: Could not create order from an empty cart.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    __FUNCTION__
                )
            );
            throw new RuntimeException(
                __('Cart is empty!', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce')
            );
        }

        return $this;
    }

    /**
     * Simplified shop flow.
     * All flows should have three sections:
     * #1 Prepare order
     * #2 Create order
     * #3 Log, handle and return response
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function processSimplified(): array
    {
        // Create order, but skip using the order reference that is used from WooCommerce.
        // This avoids order id collisions when we're on a network site where several stores may have
        // multiple tables with the same id.
        $payment = Repository::create(
            storeId: ResursBankAPI::getStoreUuidByNationalId(Data::getStoreId()),
            paymentMethodId: $this->getPaymentMethod(),
            orderLines: $this->getOrderLinesMapi(),
        );

        // Return booking result.
        // @todo SOAP returned an array with a status after bookSignedPayment, but MAPI
        // @todo differs slightly in this process. This is where we preferably handle signings which
        // @todo aso means that getResultByPaymentStatus can be replaced.
        return $this->getResultByPaymentStatus();
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function setCardData(): self
    {
        $this->API->getConnection()->setCardData(
            $this->getCustomerData('card_number')
        );

        return $this;
    }
}
