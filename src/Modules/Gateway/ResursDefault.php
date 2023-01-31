<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

/** @noinspection PhpAssignmentInConditionInspection */

namespace Resursbank\Woocommerce\Modules\Gateway;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Customer;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\Entry;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\EntryCollection;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Network\Curl\ErrorTranslator;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use ResursBank\Module\Callback as CallbackModule;
use ResursBank\Module\Data;
use ResursBank\Service\WooCommerce;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use stdClass;
use Throwable;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;
use WC_Session_Handler;
use WP_Post;

/**
 * Default payment gateway class. Written to handle payment methods dynamically but still be able to show
 * a static configuration in the admin panel. The configuration view is separated from the "payments" with
 * options slimmed to a minor view. See todo below about merging this class into the payments view again.
 *
 * Class primarily handles payments, orders and callbacks dynamically, with focus on less loss
 * of data during API communication by converting API-calls to base64-strings which prevents charset problems.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
// phpcs:ignore
class ResursDefault extends WC_Payment_Gateway
{
    /**
     * Default identifier title for this gateway.
     */
    public const TITLE = 'Resurs Bank AB';

    protected WC_Order $order;

    /**
     * WooCommerce cart. On WooCommerce-side, this is nullable, so it should only be set if available.
     */
    protected WC_Cart $cart;

    /** @var array $applicantPostData Applicant request. */
    private array $applicantPostData = [];

    /**
     * This instance payment method from Resurs Bank.
     */
    private ?PaymentMethod $paymentMethodInformation = null;

    /**
     * ResursDefault constructor.
     *
     * @param PaymentMethod|null $resursPaymentMethod Make sure gateway is reachable even if initialization has failed.
     * @throws Exception
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public function __construct(
        public readonly ?PaymentMethod $resursPaymentMethod = null
    ) {
        // A proper ID must always exist regardless of the init outcome. However, it should be set after the init
        // as the order view may want to use it differently.
        $this->id = $this->getProperGatewayId(
            resursPaymentMethod: $resursPaymentMethod
        );

        // Do not verify if this sections is allowed to initialize. It has to initialize itself each time this
        // class is called, even if the payment method itself is null (API calls is still depending on its existence).
        $this->initializePaymentMethod(paymentMethod: $resursPaymentMethod);
    }

    /**
     * This section is used by the WC Payment Gateway toggle. If we decide to support "gateway toggling", this
     * section has to be used.
     *
     * @noinspection PhpCSValidationInspection
     */
    public function update_option(mixed $key, mixed $value = ''): bool
    {
        return parent::update_option(key: $key, value: $value);
    }

    /**
     * Used by WooCommerce to get the title of the payment method.
     *
     * @noinspection PhpCSValidationInspection
     */
    public function get_title(): string
    {
        /**
         * @noinspection SpellCheckingInspection
         */
        global $theorder;

        $return = parent::get_title();

        if (!isset($theorder)) {
            return $return;
        }

        $paymentMethodTitle = $theorder->get_payment_method_title();

        // Use defaults if no order exists (this method is used on several places).
        return $this->isValidResursOrder() &&
        is_string(value: $paymentMethodTitle)
            ? $paymentMethodTitle
            : parent::get_title();
    }

    /**
     * Decide if the payment gateway is available or not. Work both in admin and checkouts, so this is where
     * we also need to check out conditions from the early instantiated cart.
     *
     * @throws Exception
     * @noinspection PhpCSValidationInspection
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
         *
         * @link https://wordpress.org/support/topic/php-notice-trying-to-get-property-total-of-non-object-2/
         */

        // If there's no cart, and we miss get_order_total in this gateway this instance probably do not belong
        // to the storefront.
        if (
            !isset($woocommerce->cart) ||
            !method_exists(object_or_class: $this, method: 'get_order_total') ||
            !isset($this->paymentMethodInformation->id)
        ) {
            // Return false if gateway in Resurs-admin is disabled and stop running the full process here.
            if (Enabled::isEnabled()) {
                return false;
            }
        }

        if (
            !Admin::isAdmin() &&
            isset($this->paymentMethodInformation) &&
            $this->paymentMethodInformation instanceof PaymentMethod
        ) {
            $return = $this->isAvailableInCheckout(return: $return);
        }

        return $return;
    }

    /**
     * Simplified checkout form field generator. This is WooCommerce-specific inherits for which we render
     * fields required by Resurs.
     *
     * @throws Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpCSValidationInspection
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
        }
    }

    /**
     * The WooCommerce-inherited process_payment method. This is where we normally want to place our
     * payment actions.
     *
     * @return array
     * @throws Exception
     * @noinspection PhpCSValidationInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function process_payment(mixed $order_id): array
    {
        $order = new WC_Order(order: $order_id);
        $this->order = $order;

        return $this->processResursOrder(order: $order);
    }

    /**
     * This is where we handle all API calls from the outside (Resurs).
     *
     * @throws Exception
     */
    public function getApiRequest(): void
    {
        if (
            isset($_REQUEST['mapi-callback']) &&
            is_string(value: $_REQUEST['mapi-callback'])
        ) {
            $response = [
                'success' => false,
                'message' => '',
            ];

            // Callback will respond and exit.
            try {
                // The way we handle callbacks now do not require a boolean the same way as before. Instead, we will
                // just handle exceptions as errors.
                CallbackModule::processCallback(
                    callbackType: CallbackType::from(
                        value: strtoupper(string: $_REQUEST['mapi-callback'])
                    )
                );
                $response['success'] = true;
            } catch (Throwable $e) {
                Config::getLogger()->error(message: $e);
                $response['message'] = $e->getMessage();
            }

            $responseCode = $response['success'] ? 202 : 408;

            Config::getLogger()->debug(
                message: 'Callback response, code ' . ($response['success'] ? 202 : 408) . '.'
            );
            Config::getLogger()->debug(
                message: print_r(value: $response, return: true)
            );

            Route::respond(
                body: json_encode(value: $response),
                code: $responseCode
            );
        }

        exit;
    }

    /**
     * Generate URL for MAPI callbacks.
     * We don't have to apply the order id to the callback URL, as the callback will be sent back as a POST (json).
     *
     * @throws IllegalValueException
     */
    public function getCallbackUrl(CallbackType $callbackType): string
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
     * Feature to check if payment method is still available in checkout after internal cart/method controls.
     */
    private function isAvailableInCheckout(bool $return): bool
    {
        try {
            $customerType = WcSession::getCustomerType();
        } catch (Throwable) {
            // Possible to-do: Make sure that defaults are set by available payment methods, not just NATURAL.
            // Normally, this is not a problem, since the merchant majority is of type LEGAL, so for now we're
            // good to go with this.
            $customerType = CustomerType::NATURAL;
        }

        // Return false on the conditions that the price is not matching min/max limits.
        // Also return false if customer types are not supported.
        if (
            !(
                (float)$this->get_order_total() >= $this->paymentMethodInformation->minPurchaseLimit &&
                (float)$this->get_order_total() <= $this->paymentMethodInformation->maxPurchaseLimit
            )
        ) {
            $return = false;
        }

        if (
            $customerType === CustomerType::LEGAL &&
            !$this->paymentMethodInformation->enabledForLegalCustomer
        ) {
            $return = false;
        } elseif (
            $customerType === CustomerType::NATURAL &&
            !$this->paymentMethodInformation->enabledForNaturalCustomer
        ) {
            $return = false;
        }

        return $return;
    }

    /**
     * Method to properly fetch an order if it is present on a current screen (the order view), making sure we
     * can display "Payment via <method>" instead of "Payment via <uuid>".
     *
     * @noinspection SpellCheckingInspection
     */
    private function getOrder(): ?WC_Order
    {
        global $theorder;
        $post = get_post(post: $_REQUEST['post'] ?? null);

        $return = null;

        // Normally we want to trust the content from $theorder. However, in some rare cases, $theorder may
        // not always be present even if we are located at the order view screen.
        if (isset($theorder)) {
            $return = $theorder;
        } elseif (
            isset($post) &&
            $post instanceof WP_Post &&
            $post->post_type === 'shop_order'
        ) {
            $return = new WC_Order(order: $post->ID);
        }

        return $return;
    }

    private function getProperGatewayId(?PaymentMethod $resursPaymentMethod = null): string
    {
        $currentOrder = $this->getOrder();

        // If no PaymentMethod is set at this point, but instead an order, the gateway is considered not
        // located in the checkout but in a maintenance state (like wp-admin/order). In this case, we
        // need to identify the current order as created with Resurs payments. Since WooCommerce is using
        // the gateway id to identify the current payment method, we also need to adapt into the initial
        // id (uuid) that was used when the order was created.
        return !isset($resursPaymentMethod) && $this->isValidResursOrder() ?
            $currentOrder->get_payment_method() : RESURSBANK_MODULE_PREFIX;
    }

    /**
     * Initialize the gateway with the current Resurs payment method model.
     * Things initialized here is mostly defaults, since we depend on features that can work independently
     * without spontaneous crashes.
     *
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

        $this->setActions();
    }

    /**
     * Initializer. It is not until we have payment method information we can start using this class for real.
     *
     * @throws Exception
     */
    private function setPaymentMethodInformation(?PaymentMethod $paymentMethod = null): void
    {
        if (!($paymentMethod instanceof PaymentMethod)) {
            return;
        }

        // Collect the entire payment method information.
        $this->paymentMethodInformation = $paymentMethod;
        $this->id = RESURSBANK_MODULE_PREFIX . '_' . $this->paymentMethodInformation->id;
        $this->title = $this->paymentMethodInformation->name ?? '';
        $this->icon = $this->getMethodIconUrl();
    }

    /**
     * Decide how to use method icons in the checkout.
     */
    private function getMethodIconUrl(): ?string
    {
        $return = null;

        // Make sure that the payment method is properly instantiated before using it.
        if (!empty($this->paymentMethodInformation)) {
            if ($this->paymentMethodInformation->isResursMethod()) {
                $return = Data::getImage(imageName: 'resurs-logo.png');
            } else {
                switch ($this->paymentMethodInformation->type) {
                    case Type::DEBIT_CARD:
                    case Type::CREDIT_CARD:
                    case Type::CARD:
                        $return = Data::getImage(
                            imageName: 'method_pspcard.svg'
                        );
                        break;

                    case Type::SWISH:
                        $return = Data::getImage(imageName: 'method_swish.png');
                        break;

                    case Type::INTERNET:
                        // Yes, "INTERNET" is a type only used for Trustly (Source: WOO-736 comments)
                        $return = Data::getImage(
                            imageName: 'method_trustly.svg'
                        );
                        break;

                    default:
                        break;
                }
            }
        }

        return $return;
    }

    /**
     * Prepare actions that WooCommerce may want to throw at us.
     */
    private function setActions(): void
    {
        add_action(
            hook_name: 'woocommerce_api_resursdefault',
            callback: [$this, 'getApiRequest']
        );
    }

    /**
     * Method that is used to check if there is an order available (formerly $theorder) and if that order
     * is available is valid as a Resurs Payment. Used to bind the payment method names properly in order views.
     */
    private function isValidResursOrder(): bool
    {
        $currentOrder = $this->getOrder();

        /** @noinspection PhpMethodOrClassCallIsNotCaseSensitiveInspection */
        return isset($currentOrder) &&
            $currentOrder instanceof WC_Order &&
            MetaData::isValidResursPayment(order: $currentOrder);
    }

    /**
     * Set up customer data for the order.
     *
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     * @throws Exception
     */
    private function getCustomer(): Customer
    {
        $customerInfoFrom = isset($_REQUEST['ship_to_different_address'])
            ? 'shipping'
            : 'billing';

        try {
            $sessionCustomerType = WcSession::getCustomerType();
        } catch (Throwable) {
            // Possible to-do: Make sure that defaults are set by available payment methods, not just NATURAL.
            // Normally, this is not a problem, since the merchant majority is of type NATURAL, so for now we're
            // good to go with this.
            $sessionCustomerType = CustomerType::NATURAL;
        }

        $governmentId = $sessionCustomerType === CustomerType::NATURAL ? $this->getCustomerData(
            key: 'government_id'
        ) :
            $this->getCustomerData(key: 'applicant_government_id');

        // Since WooCommerce uses a cookie to pick up a session, we can't use the ecom "real" session to fetch the
        // government id.
        if (
            WC()->session instanceof WC_Session_Handler &&
            empty($governmentId)
        ) {
            $governmentId = WC()->session->get(
                key: (new Session())->getKey(
                    key: Repository::SESSION_KEY_SSN_DATA
                )
            );
        }

        // @todo Also those fields for LEGAL customers.
        // $this->getCustomerData('phone')
        // $this->getCustomerData('contact_government_id')
        return new Customer(
            deliveryAddress: new Address(
                addressRow1: $this->getCustomerData(
                    key: 'address_1',
                    returnType: $customerInfoFrom
                ),
                postalArea: $this->getCustomerData(
                    key: 'city',
                    returnType: $customerInfoFrom
                ),
                postalCode: $this->getCustomerData(
                    key: 'postcode',
                    returnType: $customerInfoFrom
                ),
                countryCode: CountryCode::from(
                    value: $this->getCustomerData(
                        key: 'country',
                        returnType: $customerInfoFrom
                    )
                ),
                fullName: $this->getCustomerData(
                    key: 'full_name',
                    returnType: $customerInfoFrom
                ),
                firstName: $this->getCustomerData(
                    key: 'first_name',
                    returnType: $customerInfoFrom
                ),
                lastName: $this->getCustomerData(
                    key: 'last_name',
                    returnType: $customerInfoFrom
                ),
                addressRow2: $this->getCustomerData(
                    key: 'address_2',
                    returnType: $customerInfoFrom
                )
            ),
            customerType: $sessionCustomerType,
            contactPerson: $sessionCustomerType === CustomerType::LEGAL ?
                $this->getCustomerData(
                    key: 'full_name',
                    returnType: $customerInfoFrom
                ) : '',
            email: $this->getCustomerData(key: 'email'),
            governmentId: $governmentId,
            mobilePhone: $this->getCustomerData(key: 'mobile'),
            deviceInfo: new DeviceInfo(
                ip: $_SERVER['REMOTE_ADDR'],
                userAgent: $_SERVER['HTTP_USER_AGENT']
            )
        );
    }

    /**
     * Fetch proper customer data from applicant form request.
     */
    private function getCustomerData(string $key, string $returnType = 'billing'): string
    {
        // Primarily, this data has higher priority over internal data as this is based on custom fields.
        // applicantPostData has been sanitized prior to this point.
        $return = $this->applicantPostData[$key] ?? '';

        // If it's not in the post data, it could possibly be found in the order maintained from the order.
        $billingAddress = $this->order->get_address();
        $deliveryAddress = $this->order->get_address(type: 'shipping');

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
            $return = $customerInfo['first_name'] . ' ' . $customerInfo['last_name'];
        }

        return (string)$return;
    }

    /**
     * Get payment full method information object from ecom data.
     */
    private function getPaymentMethod(): string
    {
        return $this->paymentMethodInformation->id ?? '';
    }

    /**
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private function getReturnUrl(WC_Order $order, string $result): string
    {
        return $result === 'success' ?
            $this->get_return_url(order: $order) : html_entity_decode(
                string: $order->get_cancel_order_url()
            );
    }

    /**
     * This is where we used to handle separate flows. As we will only have two in the future,
     * this will be easier.
     *
     * @return array
     * @throws Exception
     */
    private function processResursOrder(WC_Order $order): array
    {
        // Defaults returning to WooCommerce if not successful.
        $return = [
            'result' => 'failure',
            'redirect' => $this->getReturnUrl(order: $order, result: 'failure'),
        ];

        try {
            // Order Creation
            $paymentResponse = PaymentRepository::create(
                storeId: StoreId::getData(),
                paymentMethodId: $this->getPaymentMethod(),
                orderLines: Order::getOrderLines(order: $order),
                orderReference: (string)$order->get_id(),
                customer: $this->getCustomer(),
                metadata: $this->isLoggedInUser(
                    order: $order
                ) ? $this->getLoggedInCustomerIdMeta(
                    order: $order
                ) : null,
                options: $this->getOptions(order: $order)
            );
            $return = $this->getReturnResponse(
                createPaymentResponse: $paymentResponse,
                return: $return,
                order: $order
            );

            $order->set_payment_method(
                payment_method: $this->getPaymentMethod()
            );

            if (isset($return['result']) && $return['result'] === 'success') {
                // Forget the session variable if there is a success.
                WcSession::unset(
                    key: (new Session())->getKey(
                        key: Repository::SESSION_KEY_SSN_DATA
                    )
                );
                WcSession::unset(
                    key: (new Session())->getKey(
                        key: Repository::SESSION_KEY_CUSTOMER_TYPE
                    )
                );
            }

            Metadata::setPaymentId(order: $order, id: $paymentResponse->id);
        } catch (Throwable $createPaymentException) {
            // In case we get an error from any other component than the creation, we need to rewrite this response.
            $return = [
                'result' => 'failure',
                'redirect' => $this->getReturnUrl(
                    order: $order,
                    result: 'failure'
                ),
            ];

            // Add note to notices and write to log.
            $order->add_order_note(
                note: $createPaymentException->getMessage()
            );
            Config::getLogger()->error(message: $createPaymentException);

            // Add on-screen message from failure.
            if ($createPaymentException instanceof CurlException) {
                $this->addErrorNotice(exception: $createPaymentException);
            } else {
                wc_add_notice(
                    message: $createPaymentException->getMessage(),
                    notice_type: 'error'
                );
            }
        }

        return $return;
    }

    /**
     * Attempts to extract and translate more detailed error message from CurlException from payment creation.
     *
     * @throws ConfigException
     */
    private function addErrorNotice(CurlException $exception): void
    {
        if ($exception->httpCode === 400 && !empty($exception->body)) {
            try {
                $body = json_decode(
                    json: $exception->body,
                    associative: false,
                    depth: 256,
                    flags: JSON_THROW_ON_ERROR
                );

                if (
                    isset($body->parameters) &&
                    $body->parameters instanceof stdClass
                ) {
                    foreach ($body->parameters as $property => $message) {
                        wc_add_notice(
                            message: ErrorTranslator::get(
                                errorMessage: $property . ' ' . $message
                            ),
                            notice_type: 'error'
                        );
                    }
                }
            } catch (Throwable $error) {
                Config::getLogger()->error(message: $error);
                wc_add_notice(
                    message: $exception->getMessage(),
                    notice_type: 'error'
                );
            }
        } else {
            wc_add_notice(
                message: $exception->getMessage(),
                notice_type: 'error'
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function getOptions(WC_Order $order): Options
    {
        // @todo Defaults like manual inspection, frozen payments, etc should be changed to configurable options
        // @todo through the admin panel.
        return new Options(
            initiatedOnCustomersDevice: true,
            handleManualInspection: false,
            handleFrozenPayments: true,
            redirectionUrls: new RedirectionUrls(
                customer: new ParticipantRedirectionUrls(
                    failUrl: $this->getReturnUrl(
                        order: $order,
                        result: 'failure'
                    ),
                    successUrl: $this->getReturnUrl(
                        order: $order,
                        result: 'success'
                    )
                ),
                coApplicant: null,
                merchant: null
            ),
            callbacks: new Callbacks(
                authorization: new Callback(
                    url: $this->getCallbackUrl(
                        callbackType: CallbackType::AUTHORIZATION
                    )
                ),
                management: new Callback(
                    url: $this->getCallbackUrl(
                        callbackType: CallbackType::MANAGEMENT
                    )
                )
            ),
            timeToLiveInMinutes: 120
        );
    }

    /**
     * Convert createPaymentResponse to a WooCommerce reply.
     *
     * @param array $return
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
                $return['redirect'] = $this->getReturnUrl(
                    order: $order,
                    result: 'success'
                );
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
     * Check if user is logged in during order, or not.
     */
    private function isLoggedInUser(WC_Order $order): bool
    {
        return (int)$order->get_user_id() > 0;
    }

    /**
     * Return customer user id as Resurs payment metadata from order (not current_user).
     *
     * @throws IllegalTypeException
     */
    private function getLoggedInCustomerIdMeta(WC_Order $order): ?Payment\Metadata
    {
        return new Payment\Metadata(
            custom: new EntryCollection(data: [
                new Entry(
                    key: 'externalCustomerId',
                    value: (string)$order->get_user_id()
                ),
            ])
        );
    }
}
