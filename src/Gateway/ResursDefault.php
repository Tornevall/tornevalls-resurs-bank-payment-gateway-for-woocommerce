<?php

namespace ResursBank\Gateway;

use Exception;
use JsonException;
use Resursbank\Ecommerce\Types\CheckoutType;
use ResursBank\Helpers\WooCommerce;
use ResursBank\Helpers\WordPress;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\Domain;
use TorneLIB\Utils\Generic;
use WC_Cart;
use WC_Coupon;
use WC_Order;
use WC_Payment_Gateway;
use WC_Product;
use WC_Tax;
use function count;
use function in_array;

/**
 * Class ResursDefault
 * Default payment method class. Handles payments and orders dynamically, with focus on less loss
 * of data during API communication.
 * @package Resursbank\Gateway
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
    const TYPE_HOSTED = 'hosted';
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
     * @var array $applicantPostData Applicant request.
     * @since 0.0.1.0
     */
    private $applicantPostData = [];

    /**
     * @var stdClass $paymentResponse
     * @since 0.0.1.0
     */
    private $paymentResponse;

    /**
     * @var array
     * @since 0.0.1.0
     */
    private $wcOrderData;

    /**
     * Data that will be sent between Resurs Bank and ourselves.
     * @var array $apiData
     * @since 0.0.1.0
     */
    private $apiData = [];

    /**
     * @var string $apiDataId
     * @since 0.0.1.0
     */
    private $apiDataId = '';

    /**
     * Main API. Use as primary communicator. Acts like a bridge between the real API.
     * @var Api $API
     * @since 0.0.1.0
     */
    private $API;

    /**
     * @var WC_Cart $cart
     * @since 0.0.1.0
     */
    private $cart;

    /**
     * @var WC_Order $order
     * @since 0.0.1.0
     */
    protected $order;

    /**
     * @var array $paymentMethodInformation
     * @since 0.0.1.0
     */
    private $paymentMethodInformation;

    /**
     * The iframe. Rendered once. When rendered, it won't be requested again.
     * @var string
     * @since 0.0.1.0
     */
    private $rcoFrame;

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
     * @param $resursPaymentMethodObject
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @since 0.0.1.0
     */
    public function __construct($resursPaymentMethodObject = [])
    {
        global $woocommerce;
        $this->cart = isset($woocommerce->cart) ? $woocommerce->cart : null;
        $this->generic = Data::getGenericClass();
        $this->id = Data::getPrefix('default');
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        $this->setPaymentMethodInformation($resursPaymentMethodObject);
        $this->has_fields = (Data::getCheckoutType() === self::TYPE_SIMPLIFIED || Data::getCheckoutType() === self::TYPE_HOSTED);
        $this->setFilters();
        $this->setActions();
    }

    /**
     * Initializer. It is not until we have payment method information we can start using this class for real.
     * @param $paymentMethodInformation
     * @since 0.0.1.0
     */
    private function setPaymentMethodInformation($paymentMethodInformation)
    {
        // Generic setup regardless of payment method.
        $this->setPaymentApiData();

        if (is_object($paymentMethodInformation)) {
            $this->paymentMethodInformation = $paymentMethodInformation;
            $this->id = sprintf('%s_%s', Data::getPrefix(), $this->paymentMethodInformation->id);
            $this->title = $this->paymentMethodInformation->description;
            $this->method_description = '';
            if (Data::getResursOption('payment_method_icons') === 'woocommerce_icon') {
                $this->icon = Data::getImage('resurs-logo.png');
            }

            // Applicant post data should be the final request.
            $this->applicantPostData = $this->getApplicantPostData();
        }
        // Running in RCO mode, we will only have one method available and therefore we change the current id to
        // that method.
        if (Data::getCheckoutType() === self::TYPE_RCO) {
            $this->paymentMethodInformation = new ResursCheckout();
            $this->id = sprintf('%s_%s', Data::getPrefix(), $this->paymentMethodInformation->id);
        }
    }

    /**
     * Generic setup regardless of payment method.
     * @return $this
     * @since 0.0.1.0
     */
    private function setPaymentApiData()
    {
        $this->apiData['checkoutType'] = Data::getCheckoutType();
        if (!empty(Data::getPaymentMethodBySession())) {
            $this->apiData['paymentMethod'] = Data::getPaymentMethodBySession();
        }
        $this->apiDataId = sha1(uniqid('wc-api', true));
        $this->API = new Api();

        return $this;
    }

    /**
     * @return WC_Order
     * @since 0.0.1.0
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    private function getApplicantPostData()
    {
        $realMethodId = $this->getRealMethodId();
        $return = [];
        // Skip the scraping if this is not a payment.
        if ($this->isPaymentReady()) {
            foreach ($_REQUEST as $requestKey => $requestValue) {
                if (preg_match(sprintf('/%s$/', $realMethodId), $requestKey)) {
                    $applicantDataKey = (string)preg_replace(
                        sprintf(
                            '/%s_(.*?)_%s/',
                            Data::getPrefix(),
                            $realMethodId
                        ),
                        '$1',
                        $requestKey
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
        return preg_replace('/^trbwc_/', '', $this->id);
    }

    /**
     * @param $pageId
     * @return int|mixed
     * @since 0.0.1.0
     */
    public function getTermsByRco($pageId)
    {
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $pageId = 0;
        }
        return $pageId;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    private function isPaymentReady()
    {
        return (isset($_REQUEST['payment_method'], $_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'checkout');
    }

    /**
     * @since 0.0.1.0
     */
    private function setFilters()
    {
        add_filter('woocommerce_order_button_html', [$this, 'getOrderButtonHtml']);
        add_filter('woocommerce_checkout_fields', [$this, 'getCheckoutFields']);
        add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
        add_filter('woocommerce_get_terms_page_id', [$this, 'getTermsByRco'], 1);
        add_filter('woocommerce_order_get_payment_method_title', [$this, 'getPaymentMethodTitle'], 10, 2);
    }

    /**
     * Internal payment method title fetching, based on checkout type and the real payment method when in RCO.
     *
     * @return string
     * @since 0.0.1.0
     */
    public function get_title()
    {
        global $theorder;

        $return = parent::get_title();

        if (!empty($theorder)) {
            try {
                $internalPaymentTitle = $this->getPaymentMethodTitle($return, $theorder);
                if (!empty($internalPaymentTitle)) {
                    $return = $internalPaymentTitle;
                }
            } catch (\Exception $e) {
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
                    $paymentMethodDetails = json_decode(Data::getResursOption('paymentMethods'), JSON_THROW_ON_ERROR);
                    if (is_array($paymentMethodDetails)) {
                        foreach ($paymentMethodDetails as $method) {
                            if (isset($method['id']) && $method['id'] === $internalTitle) {
                                $title = $method[Data::getResursOption('rco_method_titling')];
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }
        return $title;
    }

    /**
     * @since 0.0.1.0
     */
    private function setActions()
    {
        add_action('woocommerce_api_resursdefault', [$this, 'getApiRequest']);
        add_action('wp_enqueue_scripts', [$this, 'getHeaderScripts'], 0);
        if (Data::getCheckoutType() === self::TYPE_RCO) {
            add_action(
                sprintf('woocommerce_%s', Data::getResursOption('rco_iframe_position')),
                [$this, 'getRcoIframe']
            );
        }
    }

    /**
     * Enqueue scripts that is necessary for RCO (v2) to run properly.
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getHeaderScripts()
    {
        if (Data::getCheckoutType() === self::TYPE_RCO) {
            WooCommerce::setSessionValue(WooCommerce::$inCheckoutKey, true);

            $this->processRco();
        }
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUndefinedFieldInspection
     */
    public function is_available()
    {
        $return = parent::is_available();
        $customerType = Data::getCustomerType();
        if (isset($this->paymentMethodInformation, $this->paymentMethodInformation->minLimit)) {
            $minMax = Api::getResurs()->getMinMax(
                $this->get_order_total(),
                $this->paymentMethodInformation->minLimit,
                $this->paymentMethodInformation->maxLimit
            );
            if (!$minMax) {
                $return = false;
            }

            // We decide at this level if the payment method should be available,
            // based on current chosen country. Beware of the admin parts.
            if (!is_admin() && !empty($customerType) && $return) {
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
     * How to handle the submit order button. For future RCO.
     *
     * @param $classButtonHtml
     * @return mixed
     * @since 0.0.1.0
     */
    public function getOrderButtonHtml($classButtonHtml)
    {
        if (Data::getCheckoutType() !== self::TYPE_RCO) {
            return $classButtonHtml;
        }
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
        $url = add_query_arg('page', $_REQUEST['page'], $url);
        $url = add_query_arg('tab', $_REQUEST['tab'], $url);
        wp_safe_redirect($url);
        die('Deprecated space');
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function payment_fields()
    {
        $fieldHtml = null;
        // If not here, no fields are required.
        /** @noinspection PhpUndefinedFieldInspection */
        $requiredFields = (array)FormFields::getSpecificTypeFields($this->paymentMethodInformation->type);

        if (Data::getCheckoutType() === self::TYPE_RCO) {
            // TODO: No fields should be active on RCO. Make sure we never land here at all.
            return;
        }

        if (count($requiredFields)) {
            foreach ($requiredFields as $fieldName) {
                $fieldValue = null;
                switch ($fieldName) {
                    case 'government_id':
                        $fieldValue = WooCommerce::getSessionValue('identification');
                        break;
                    default:
                        break;
                }
                $fieldHtml .= $this->generic->getTemplate('checkout_paymentfield.phtml', [
                    'displayMode' => $this->getDisplayableField($fieldName) ? '' : 'none',
                    'methodId' => isset($this->paymentMethodInformation->id) ?
                        $this->paymentMethodInformation->id : '?',
                    'fieldSize' => WordPress::applyFilters('getPaymentFieldSize', 24, $fieldName),
                    'streamLine' => Data::getResursOption('streamline_payment_fields'),
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

            echo $fieldHtml;
        }
    }

    /**
     * @param $fieldName
     * @return bool
     * @since 0.0.1.0
     */
    private function getDisplayableField($fieldName)
    {
        return !(Data::getResursOption('streamline_payment_fields') ||
            !FormFields::canDisplayField($fieldName));
    }

    /**
     * @param int $order_id
     * @return array|void
     * @throws Exception
     * @since 0.0.1.0
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $this->order = $order;
        // Return template.
        $return = [
            'ajax_success' => true,
            'result' => 'failure',
            'redirect' => $this->getReturnUrl($order),
        ];

        // Developers can put their last veto here.
        $allowInternalPaymentProcess = WordPress::applyFilters('canProcessOrder', true);

        // We will most likely land here if order_awaiting_payment was null at first init and checkout was of type RCO.
        // As RCO is very much backwards handled, we have to update meta data on order succession so that
        // we can match the correct order on successUrl later on.
        $metaCheckoutType = Data::getOrderMeta($order_id, 'checkoutType');
        if (empty($metaCheckoutType)) {
            $this->setOrderCheckoutMeta($order_id);
            if ($metaCheckoutType === ResursDefault::TYPE_RCO && !empty(Data::getPaymentMethodBySession())) {
                $order->set_payment_method(Data::getPaymentMethodBySession());
            }
        }

        $this->preProcessOrder($order);
        if ($allowInternalPaymentProcess) {
            $return = $this->processResursOrder($order);
        } else {
            $return = WordPress::applyFilters('canProcessOrderResponse', $return, $order);
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
        return $result === 'success' || (bool)$result === true ?
            $returnUrl : html_entity_decode($order->get_cancel_order_url());
    }

    /**
     * @param WC_Order $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private function preProcessOrder($order)
    {
        $this->apiData['wc_order_id'] = $order->get_id();
        $this->apiData['preferred_id'] = $this->getPaymentId();
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                'setPreferredId:%s',
                $this->apiData['preferred_id']
            )
        );
        $this->API->getConnection()->setPreferredId($this->apiData['preferred_id']);
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getPaymentId()
    {
        switch (Data::getResursOption('order_id_type')) {
            case 'postid':
                $return = $this->order->get_id();
                break;
            case 'ecom':
            default:
                $sessionPaymentId = WooCommerce::getSessionValue('rco_order_id');
                // It is necessary to fetch payment id from session if exists, so we can keep it both in frontend
                // and the backend API. If not set, we'll let ecomPHP fetch a new.
                $return = !empty($sessionPaymentId) ? $sessionPaymentId : Api::getResurs()->getPreferredPaymentId();
                break;
        }

        return (string)$return;
    }

    /**
     * @param WC_Order $order
     * @return array
     * @throws Exception
     */
    private function processResursOrder($order)
    {
        // Available options: simplified, hosted, rco. Methods should exists for each of them.
        $checkoutRequestType = sprintf('process%s', ucfirst(Data::getCheckoutType()));

        if (method_exists($this, $checkoutRequestType)) {
            $this->order->add_order_note(
                sprintf(__('Resurs Bank processing order (%s).', 'trbwc'), $checkoutRequestType)
            );
            // Automatically process orders with a checkout type that is supported by this plugin.
            // Checkout types will become available as the method starts to exist.

            Data::canLog(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __('%s: Initialize Resurs Bank process, order %s (%s) via %s.', 'trbwc'),
                    __FUNCTION__,
                    $this->order->get_id(),
                    $this->getPaymentId(),
                    $checkoutRequestType
                )
            );

            $return = $this->{$checkoutRequestType}($order);
        } else {
            Data::setLogError(
                sprintf(
                    'Merchant is trying to run this plugin with an unsupported checkout type (%s).',
                    $checkoutRequestType
                )
            );
            throw new RuntimeException(
                __(
                    'Chosen checkout type is currently unsupported'
                ),
                404
            );
        }
        if (is_array($return)) {
            Data::canLog(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __('%s: Order %s returned "%s" to WooCommerce.', 'trbwc'),
                    __FUNCTION__,
                    $order->get_id(),
                    $return['result']
                )
            );
        } else {
            Data::setLogError(
                sprintf(
                    __('$return is not an object as expected in function %s (Checkout request type is %s).', 'trbwc'),
                    __FUNCTION__,
                    $checkoutRequestType
                )
            );
        }

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getApiRequest()
    {
        $finalRedirectUrl = wc_get_cart_url();

        if (isset($_REQUEST['c'])) {
            WooCommerce::getHandledCallback();
            die();
        }

        if (isset($_REQUEST['apiData'])) {
            $this->getApiByRco();
            $this->setApiData(json_decode(
                (new Strings())->base64urlDecode($_REQUEST['apiData']),
                true
            ));

            $this->order = $this->getCurrentOrder();

            $this->apiData['isReturningCustomer'] = false;
            if ($this->getApiValue('wc_order_id') && $this->getApiValue('preferred_id')) {
                $this->apiData['isReturningCustomer'] = true;
            }
            Data::canLog(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    __('API data request: %s.', 'trbwc'),
                    $this->apiData['isReturningCustomer'] ?
                        __('Customer returned from Resurs Bank. WooCommerce order validation in progress.', 'trbwc') :
                        __('wc_order_id + preferred_id not present', 'trbwc')
                )
            );
            $this->wcOrderData = Data::getOrderInfo($this->order);

            if ($this->isSuccess() && $this->setFinalSigning()) {
                if ($this->getCheckoutType() === self::TYPE_SIMPLIFIED) {
                    // When someone returns with a successful call.
                    if (Data::getOrderMeta('signingRedirectTime', $this->wcOrderData) &&
                        !Data::getOrderMeta('signingOk', $this->wcOrderData)
                    ) {
                        $finalRedirectUrl = $this->get_return_url($this->order);
                    }
                } elseif ($this->getCheckoutType() === self::TYPE_HOSTED || $this->getCheckoutType() === self::TYPE_RCO) {
                    $finalRedirectUrl = $this->get_return_url($this->order);
                }
            } else {
                // Landing here is complex, but this part of the method is based on failures.
                $signing = false;       // Initially, we presume no signing was in action.
                if (Data::getOrderMeta('signingRedirectTime', $this->wcOrderData) &&
                    !Data::getOrderMeta('signingOk', $this->wcOrderData)
                ) {
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
                Data::canLog(Data::CAN_LOG_ORDER_EVENTS, $cancelNote);
                // Now that we have all necessary data, we'll cancelling the order.
                $this->updateOrderStatus(
                    self::STATUS_CANCELLED,
                    $cancelNote
                );
                // And then we prepare WooCommerce to show this visually.
                wc_add_notice($setCustomerNotice);
            }
        }

        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                __('Finishing. Ready to redirect customer. Using URL %s', 'trbwc'),
                $finalRedirectUrl
            )
        );

        wp_safe_redirect($finalRedirectUrl);
        die;
    }

    /**
     * If API request is based on RCO, we can not entirely trust the landing page API content.
     * In that case we have to remerge some data from the session instead as the "signing success url"
     * is empty when the iframe is rendered.
     *
     * @return $this
     * @throws JsonException
     * @since 0.0.1.0
     */
    private function getApiByRco()
    {
        $baseHandler = new Strings();
        $apiRequestContent = json_decode($baseHandler->base64urlDecode($_REQUEST['apiData']), true);

        if ($apiRequestContent['checkoutType'] === ResursDefault::TYPE_RCO) {
            $requestSession = [
                'preferred_id' => 'rco_order_id',
                'wc_order_id' => 'order_awaiting_payment',
            ];
            $apiRequestContent = $this->getSessionApiData($apiRequestContent, $requestSession);
            // Reencode the data again.
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
            if ((!empty($sessionValue) && !isset($apiRequestContent[$itemKey])) || empty($apiRequestContent[$itemKey])) {
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
    private function setApiData($apiArray)
    {
        $this->apiData = array_merge($this->apiData, $apiArray);
        return $this;
    }

    /**
     * @return WC_Order
     * @since 0.0.1.0
     */
    private function getCurrentOrder()
    {
        $return = new WC_Order($this->getApiValue('wc_order_id'));
        $awaitingOrderId = WooCommerce::getSessionValue('order_awaiting_payment');
        if (!$return->get_id() && $awaitingOrderId) {
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
     * @return array|mixed|string
     * @since 0.0.1.0
     */
    private function isSuccess()
    {
        return $this->getApiValue('success');
    }

    /**
     * Final signing: Checks and update order if signing was required initially. Let it through on hosted but
     * keep logging the details.
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setFinalSigning()
    {
        $return = false;
        try {
            if (!($lastExceptionCode = Data::getOrderMeta('bookSignedPaymentExceptionCode', $this->order)) ||
                $this->getCheckoutType() === self::TYPE_HOSTED
            ) {
                $bookSignedOrderReference = Data::getOrderMeta('resursReference', $this->wcOrderData);
                $this->setFinalSigningNotes($bookSignedOrderReference);
                // Signing is only necessary for simplified flow.
                if ($this->getCheckoutType() === self::TYPE_SIMPLIFIED) {
                    $this->paymentResponse = $this->API->getConnection()->bookSignedPayment(
                        $bookSignedOrderReference
                    );
                    $this->getResultByPaymentStatus();
                }
                $return = true;
            } else {
                $this->setFinalSigningProblemNotes($lastExceptionCode);
            }
        } catch (Exception $booksignedException) {
            $this->setFinalSigningExceptionNotes($booksignedException);
        }

        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private function getCheckoutType()
    {
        return (string)$this->getApiValue('checkoutType');
    }

    /**
     * @param $bookSignedOrderReference
     * @since 0.0.1.0
     */
    private function setFinalSigningNotes($bookSignedOrderReference)
    {
        $customerSignedMessage = sprintf(
            __('Customer returned from Resurs Bank to complete order %s.', 'trbwc'),
            $bookSignedOrderReference
        );
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            $customerSignedMessage
        );
        $this->order->add_order_note($customerSignedMessage);
    }

    /**
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getResultByPaymentStatus()
    {
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s bookPaymentStatus order %s:%s',
                __FUNCTION__,
                $this->order->get_id(),
                $this->getBookPaymentStatus()
            )
        );
        switch ($this->getBookPaymentStatus()) {
            case 'FINALIZED':
                $this->setSigningMarked();
                WC()->session->set('order_awaiting_payment', true);
                $this->updateOrderStatus(
                    self::STATUS_FINALIZED,
                    __('Order is debited and completed.', 'trbwc')
                );
                $return = $this->getResult('success');
                break;
            case 'BOOKED':
                $this->setSigningMarked();
                $this->updateOrderStatus(
                    self::STATUS_BOOKED,
                    __('Order is booked and ready to handle.', 'trbwc')
                );
                $return = $this->getResult('success');
                break;
            case 'FROZEN':
                $this->setSigningMarked();
                $this->updateOrderStatus(
                    self::STATUS_FROZEN,
                    __('Order is frozen and waiting for manual inspection.', 'trbwc')
                );
                $return = $this->getResult('success');
                break;
            case 'SIGNING':
                Data::setOrderMeta($this->order, 'signingOk', false);
                Data::setOrderMeta($this->order, 'signingRedirectTime', time());
                $this->updateOrderStatus(
                    self::STATUS_SIGNING,
                    __('Resurs Bank requires signing on this order. Customer redirected.', 'trbwc')
                );
                $return = $this->getResult('success', $this->getBookSigningUrl());
                break;
            case 'FAILED':
            case 'DENIED':
            default:
                $this->order->add_order_note(
                    sprintf(__('The booking failed with status %s. Customer notified in checkout.', 'trbwc')),
                    $this->getBookPaymentStatus()
                );
                wc_add_notice(__(
                    'The payment can not complete. Contact customer services for more information.',
                    'trbwc'
                ), 'error');
                $return = $this->getResult('failed');
                break;
        }
        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private function getBookPaymentStatus()
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
        return isset($this->paymentResponse->$key) ? $this->paymentResponse->$key : '';
    }

    /**
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setSigningMarked()
    {
        $return = false;
        if (Data::getOrderMeta($this->order, 'signingRedirectTime') &&
            Data::getOrderMeta($this->order, 'bookPaymentStatus')
        ) {
            $return = Data::setOrderMeta($this->order, 'signingOk', true);
        }
        return $return;
    }

    /**
     * @param $woocommerceStatus
     * @param $statusNotification
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
            $this->order->add_order_note($statusNotification);
        } else {
            $this->order->update_status(
                $woocommerceStatus,
                $statusNotification
            );
        }

        $this->order->save();
    }

    /**
     * @param $status
     * @param $redirect
     * @return array
     * @since 0.0.1.0
     */
    private function getResult($status, $redirect = null)
    {
        if (empty($redirect)) {
            $redirect = $status === 'failed' ?
                $this->order->get_cancel_order_url() : $this->get_return_url($this->order);
        }
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf('Result: %s, Redirect to %s.', $status, $redirect)
        );
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
     * @since 0.0.1.0
     */
    private function setFinalSigningProblemNotes($lastExceptionCode)
    {
        $this->order->add_order_note(
            sprintf(
                __(
                    'Booking this signed payment has been running multiple times but failed ' .
                    'with exception %s.',
                    'trbwc'
                ),
                $lastExceptionCode
            )
        );
        Data::setLogNotice(
            sprintf(
                __(
                    'Tried to book signed payment but skipped: This has happened before and an ' .
                    'exception with code %d occurred that time.',
                    'trbwc'
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
        Data::setLogException($bookSignedException);
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
     * @param $signing
     * @return string
     * @since 0.0.1.0
     */
    private function getCustomerAfterSigningNotices($signing)
    {
        $return = __(
            'Could not complete your order. Please contact support for more information.',
            'trbwc'
        );

        if ($signing) {
            $return = (string)__(
                'Could not complete order due to signing problems. Did you cancel your order?',
                'trbwc'
            );
        }

        return (string)$return;
    }

    /**
     * @param $signing
     * @return string
     * @since 0.0.1.0
     */
    private function getCancelNotice($signing)
    {
        return sprintf(
            __('Customer returned via urlType "%s" - failed or cancelled payment (signing required: %s).', 'trbwc'),
            $this->getUrlType(),
            $signing ? __('Yes', 'trbwc') : __('No', 'trbwc')
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
        echo WordPress::applyFilters(
            'getRcoContainerHtml',
            sprintf(
                '<div id="resursbank_rco_container"></div>'
            )
        );
    }

    /**
     * Simplified shop flow.
     * All flows should have three sections:
     * #1 Prepare order
     * #2 Create order
     * #3 Log, handle and return response
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function processSimplified()
    {
        // Section #1: Prepare order.
        $this->API->setCheckoutType(CheckoutType::SIMPLIFIED_FLOW);
        $this->API->setFraudFlags();
        $this->setOrderData();
        $this->setCreatePaymentNotice(__FUNCTION__);

        // Section #2: Create Order
        $this->paymentResponse = $this->API->getConnection()->createPayment(
            $this->getPaymentMethod()
        );

        // Section #3: Log, handle and return response
        Data::setOrderMeta($this->order, 'bookPaymentStatus', $this->getBookPaymentStatus());
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s: %s:bookPaymentStatus:%s, signingUrl: %s',
                __FUNCTION__,
                $this->getPaymentId(),
                $this->getBookPaymentStatus(),
                $this->getBookSigningUrl()
            )
        );

        // Return booking result.
        return $this->getResultByPaymentStatus();
    }

    /**
     * Global order data handler. Things that happens to all flows.
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setOrderData()
    {
        Data::setDeveloperLog(__FUNCTION__, 'Start.');

        // Handle customer data from checkout only if this is not RCO (unless there is an order ready).
        // RCO handles them externally.
        if (!$this->order && Data::getCheckoutType() !== self::TYPE_RCO) {
            $this
                ->setCustomer();
        }
        $this
            ->setCustomerId()
            ->setStoreId()
            ->setOrderLines()
            ->setCoupon()
            ->setShipping()
            ->setFee()
            ->setSigning();
        Data::setDeveloperLog(__FUNCTION__, 'Done.');

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setSigning()
    {
        $this->API->getConnection()->setSigning(
            $this->getSigningUrl(['success' => true, 'urlType' => 'success']),
            $this->getSigningUrl(['success' => false, 'urlType' => 'fail']),
            Data::getResursOption('always_sign'),
            $this->getSigningUrl(['success' => false, 'urlType' => 'back'])
        );

        // Running in RCO mode we most likely don't have any order to put metadata into, yet.
        if (!$this->order && Data::getCheckoutType() !== self::TYPE_RCO) {
            // The data id is the hay value for finding prior orders on landing pages etc.
            $this->setOrderCheckoutMeta($this->order);
        }

        return $this;
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
        Data::setOrderMeta($order, 'resursReference', $this->getPaymentId());
        if (!empty(Data::getPaymentMethodBySession())) {
            Data::setOrderMeta($order, 'paymentMethod', Data::getPaymentMethodBySession());
        }
    }

    /**
     * @param null $params
     * @return string
     * @since 0.0.1.0
     */
    private function getSigningUrl($params = null)
    {
        $wcApi = WooCommerce::getWcApiUrl();
        $signingBaseUrl = add_query_arg('apiDataId', $this->apiDataId, $wcApi);
        $signingBaseUrl = add_query_arg('apiData', $this->getApiData((array)$params, true), $signingBaseUrl);

        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                __('ECom setSigning: %s', 'trbwc'),
                $signingBaseUrl
            )
        );
        Data::setDeveloperLog(
            __FUNCTION__,
            sprintf(
                __('ECom setSigning parameters: %s', 'trbwc'),
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
    private function getApiData($addArray = null, $encode = null)
    {
        $return = json_encode(array_merge((array)$addArray, $this->apiData));

        if ((bool)$encode) {
            $return = (new Strings())->base64urlEncode($return);
        }

        return (string)$return;
    }

    /**
     * @return $this
     * @since 0.0.1.0
     * @todo Complete this.
     */
    private function setFee()
    {
        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setShipping()
    {
        // Add when not free.
        if ($this->cart->get_shipping_total() > 0) {
            Data::setDeveloperLog(
                __FUNCTION__,
                sprintf('Apply shipping fee %s', $this->cart->get_shipping_total())
            );

            // Rounding is ironically used with wc settings.
            $this->API->getConnection()->addOrderLine(
                WordPress::applyFilters('getShippingName', 'shipping'),
                WordPress::applyFilters('getShippingDescription', __('Shipping', 'rbwc')),
                $this->cart->get_shipping_total(),
                (float)round(
                    $this->cart->get_shipping_tax() / $this->cart->get_shipping_total(),
                    wc_get_price_decimals()
                ) * 100,
                $this->getFromProduct('unit', null),
                'SHIPPING_FEE'
            );
        }

        return $this;
    }

    /**
     * @param string $getValueType
     * @param WC_Product $productObject
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getFromProduct($getValueType, $productObject)
    {
        $return = '';

        switch ($getValueType) {
            case 'artNo':
                $return = $this->getProperArticleNumber($productObject);
                break;
            case 'title':
                $return = !empty($useTitle = $productObject->get_title()) ? $useTitle : __(
                    'Article description is missing.',
                    'trbwc'
                );
                break;
            case 'unitAmountWithoutVat':
                // Special reflection of what Resurs Bank wants.
                $return = wc_get_price_excluding_tax($productObject);
                break;
            case 'vatPct':
                $return = $this->getProductVat($productObject);
                break;
            case 'unit':
                // Using default measure from ECom for now.
                $return = $this->API->getConnection()->getDefaultUnitMeasure();
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
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setCoupon()
    {
        if (wc_coupons_enabled()) {
            $coupons = $this->cart->get_coupons();

            /**
             * @var string $code
             * @var WC_Coupon $coupon
             */
            foreach ($coupons as $code => $coupon) {
                $couponDescription = $coupon->get_description();
                if (empty($couponDescription)) {
                    $couponDescription = $coupon->get_code();
                }

                $discardCouponVat = (bool)Data::getResursOption('discard_coupon_vat');
                $exTax = 0 - $this->cart->get_coupon_discount_amount($code);
                $incTax = 0 - $this->cart->get_coupon_discount_amount($code, false);
                $vatPct = (($incTax - $exTax) / $exTax) * 100;

                Data::setDeveloperLog(
                    __FUNCTION__,
                    sprintf(
                        'Apply coupon %s with VAT %d. Setting "discard_coupon_vat" is %s.',
                        $coupon->get_id(),
                        $vatPct,
                        $discardCouponVat ? 'true' : 'false'
                    )
                );

                $this->API->getConnection()->addOrderLine(
                    $coupon->get_id(),
                    WordPress::applyFilters(
                        'getCouponDescription',
                        $couponDescription
                    ),
                    0 - $this->cart->get_coupon_discount_amount(
                        $coupon->get_code(),
                        WordPress::applyFilters('couponsExTax', !$discardCouponVat, $coupon)
                    ),
                    WordPress::applyFilters('getCouponVatPct', !$discardCouponVat ? $vatPct : 0),
                    $this->getFromProduct('unit', null),
                    'DISCOUNT'
                );
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setOrderLines()
    {
        if (WooCommerce::getValidCart()) {
            $currentCart = WooCommerce::getValidCart(true);
            foreach ($currentCart as $item) {
                /**
                 * Data object is of type WC_Product_Simple actually.
                 * @var WC_Product $productData
                 */
                $productData = $item['data'];

                if ($productData !== null) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf(
                            'Add orderline %s.',
                            $productData->get_id()
                        )
                    );
                    $this->setOrderRow('ORDER_LINE', $productData, $item);
                }
            }
        } else {
            Data::setLogError(sprintf(
                __('%s: Could not create order from an empty cart.', 'trbwc')
            ));
            throw new RuntimeException(
                __('Cart is empty!', 'trbwc')
            );
        }

        return $this;
    }

    /**
     * @param string $rowType
     * @param WC_Product $productData
     * @param array $item
     * @return ResursDefault
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setOrderRow($rowType, $productData, $item)
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
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setStoreId()
    {
        /** @noinspection PhpDeprecationInspection */
        $deprecatedStoreId = WordPress::applyFiltersDeprecated('set_storeid', null);
        $storeId = (int)WordPress::applyFilters('setStoreId', $deprecatedStoreId);
        if ($storeId) {
            $this->API->getConnection()->setStoreId($storeId);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setCustomerId()
    {
        $customerId = $this->getCustomerId();
        if ($customerId) {
            $this->API->getConnection()->setMetaData('CustomerId', $customerId);
        }
        return $this;
    }

    /**
     * @return int
     * @since 0.0.1.0
     */
    private function getCustomerId()
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
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setCustomer()
    {
        $governmentId = $this->getCustomerData('government_id');
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

        Data::setDeveloperLog(__FUNCTION__, 'setApiCustomer:$_POST');
        $this->API->getConnection()->setCustomer(
            $this->getCustomerData('government_id'),
            $this->getCustomerData('phone'),
            $this->getCustomerData('mobile'),
            $this->getCustomerData('email'),
            'NATURAL',
            $this->getCustomerData('government_id_contact')
        );

        return $this;
    }

    /**
     * @param $key
     * @param $returnType
     * @return string
     * @since 0.0.1.0
     */
    private function getCustomerData($key, $returnType = null)
    {
        $return = isset($this->applicantPostData[$key]) ? $this->applicantPostData[$key] : '';

        if ($key === 'mobile' && isset($return['phone']) && !$return) {
            $return = $return['phone'];
        }
        if ($key === 'phone' && isset($return['mobile']) && !$return) {
            $return = $return['phone'];
        }

        // If it's not in the postdata, it could possibly be found in the order maintained from the order.
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

        Data::canLog(Data::CAN_LOG_JUNK, sprintf('%s:%s,%s', __FUNCTION__, $key, $return));

        return (string)$return;
    }

    /**
     * @param $fromFunction
     * @since 0.0.1.0
     */
    private function setCreatePaymentNotice($fromFunction)
    {
        // Create payment for simplified (log in all flows).
        Data::setDeveloperLog(
            $fromFunction,
            sprintf('createPayment %s init', $this->getPaymentMethod())
        );
    }

    /**
     * Get payment method from ecom data.
     * @return string
     * @since 0.0.1.0
     */
    private function getPaymentMethod()
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return (string)isset($this->paymentMethodInformation->id) ? $this->paymentMethodInformation->id : '';
    }

    /**
     * Hosted checkout flow. Like simplified, but less data.
     * All flows should have three sections:
     * #1 Prepare order
     * #2 Create order
     * #3 Log, handle and return response
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function processHosted()
    {
        $this->API->setCheckoutType(CheckoutType::HOSTED_FLOW);
        $this->API->setFraudFlags();
        $this->setOrderData();
        $this->setCreatePaymentNotice(__FUNCTION__);

        // Section #2: Create Order
        $this->paymentResponse = $this->API->getConnection()->createPayment(
            $this->getPaymentMethod()
        );

        // Section #3: Log, handle and return response

        // Make sure the response contains an url (not necessarily a https-based URL in staging, so compare with http).
        if (strncmp($this->paymentResponse, 'http', 4) === 0) {
            $return = $this->getResult('success', $this->paymentResponse);
        } else {
            $return = $this->getResult('failed');
        }

        Data::setOrderMeta($this->order, 'bookPaymentStatus', $this->paymentResponse);
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                '%s: %s:bookPaymentStatus:hosted:%s',
                __FUNCTION__,
                $this->getPaymentId(),
                $this->paymentResponse
            )
        );

        return $return;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function setCardData()
    {
        $this->API->getConnection()->setCardData(
            $this->getCustomerData('card_number')
        );

        return $this;
    }

    /**
     * Standard Resurs Checkout. Not interceptor ready.
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function processRco()
    {
        // setFraudFlags can not be set for this checkout type.
        $this->API->setCheckoutType(CheckoutType::RESURS_CHECKOUT);

        // Prevent recreation of an iframe, when it is not needed.
        // This could crash the order completion (at the successUrl).
        if (WooCommerce::getValidCart() && WooCommerce::getSessionValue(WooCommerce::$inCheckoutKey)) {
            $this->setOrderData();
            $this->setCreatePaymentNotice(__FUNCTION__);
            $paymentId = $this->API->getConnection()->getPreferredPaymentId();
            $this->rcoFrame = $this->API->getConnection()->createPayment($paymentId);
            $this->rcoFrameData = $this->API->getConnection()->getFullCheckoutResponse();
            $this->rcoFrameData->legacy = $this->paymentMethodInformation->isLegacyIframe($this->rcoFrameData);

            // Since legacy is still a thing, we still need to fetch this variable, even if it is slightly isolated.
            WooCommerce::setSessionValue('rco_legacy', $this->rcoFrameData->legacy);

            // Store the payment id for later use.
            WooCommerce::setSessionValue('rco_order_id', $paymentId);

            $urlList = (new Domain())->getUrlsFromHtml($this->rcoFrameData->script);
            if (isset($this->rcoFrameData->script) && !empty($this->rcoFrameData->script) && count($urlList)) {
                $this->rcoFrameData->originHostName = $this->API->getConnection()->getIframeOrigin($this->rcoFrameData->baseUrl);
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
            }
        }
    }
}
