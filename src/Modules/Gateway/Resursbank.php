<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\SetMethodCountryRestriction;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Admin as AdminUtility;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WcSession;
use Resursbank\Woocommerce\Util\WooCommerce;
use Resursbank\Woocommerce\Util\WordPress;
use Throwable;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;

use function get_option;

/**
 * Resurs Bank payment gateway.
 * This class tends to be longer than necessary. We should ignore inspection warnings.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
// phpcs:ignore
class Resursbank extends WC_Payment_Gateway
{
    /** @var bool Errors that should be catchable in an early state, to prevent render "bad" payment methods. */
    public bool $canRenderPaymentFields = true;

    public ?string $type = '';

    /** @var int Internal sort order. */
    public int $sortOrder = 0;

    /** @var string Pre-generated USP text. */
    private string $uspText = '';

    /**
     * Error message for Blocks checkout (replaces global variable).
     * Required for Blocks checkout: error messages must be handled through process_payment(),
     * as wc_add_notice() alone is not respected by Blocks.
     */
    private static ?string $blockCreateErrorMessage = null;

    /**
     * Session key for tracking the last order with a Resurs payment.
     */
    private const SESSION_KEY_LAST_RESURS_ORDER = 'resursbank_last_payment_order';

    /**
     * Setup.
     */
    public function __construct(
        private ?PaymentMethod $method = null,
        int $sortOrder = 0
    ) {
        // Assign default property values for this gateway.
        $this->id = WordPress::getModulePrefix();
        $this->plugin_id = 'resursbank-mapi';
        $this->title = 'Resurs Bank';
        $this->method_description = 'Resurs Bank Gateway';
        $this->has_fields = true;
        $this->enabled = 'yes';
        $this->type = null;
        $this->sortOrder = $sortOrder;

        // Resolving payment method, setting the proper id for the gateway.
        $this->resolveNullableMethod();

        // Mirror title to method_title.
        $this->method_title = $this->title;

        // When the blocks editor redirects admins to woocommerce internal sections
        // for handling payment methods, we need to redirect them back to the correct
        // location since our methods are not editable from WooCommerce.
        $section = WordPress::getQueryParam(key: 'section');

        if (
            $section !== '' &&
            isset($method->id) &&
            is_string(value: $this->id) &&
            $method->id !== RESURSBANKABPAYMENTS_MODULE_PREFIX
        ) {
            // Redirects to the correct section if the wrong section is requested when the section is set to a method ID.
            AdminUtility::redirectAtWrongSection(method: $method->id);
        }

        $this->generatePaymentFieldsHtml();
    }

    /**
     * Get information about a payment method from Resurs Bank that is normally unavailable from the gateway.
     *
     * @noinspection PhpUnused
     */
    public function getMethodInfo(): ?PaymentMethod
    {
        return $this->method;
    }

    /**
     * Render info about our payment methods in their section at checkout.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public function payment_fields(): void
    {
        if ($this->uspText === '') {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->uspText;
    }

    /**
     * Create Resurs Bank payment and assign additional metadata to WC_Order.
     *
     * @throws Exception
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     * @phpcs:ignore WordPress.Security.NonceVerification.Recommended -- order_id is from WooCommerce internal processing, not user input
     */
    public function process_payment(mixed $order_id): array
    {
        $order = new WC_Order(order: $order_id);

        // Handle any existing payment - returns redirect URL if cancel failed
        // (payment is in progress and customer should continue existing session)
        $existingSessionUrl = $this->handleExistingPayment(order: $order);

        if ($existingSessionUrl !== null) {
            // Redirect to existing payment session instead of creating new one
            return [
                'result' => 'success',
                'redirect' => $existingSessionUrl,
            ];
        }

        // Generate a unique cancel token for this payment attempt.
        // This token is included in the failure URL and stored on the order.
        // When the failure page is reached, we compare tokens - if they don't match,
        // it means the payment was cancelled and a new one created, so we skip
        // order cancellation. We want to avoid unnecessary order cancellations
        // because the cart is still open in WooCommerce (the order remains in a
        // modifiable "Pending Payment" state), allowing the customer to retry.
        // Without this safeguard, each cancelled payment request would trigger
        // an order cancellation, potentially creating many cancelled orders when
        // a customer simply retries checkout.
        $cancelToken = $this->generateCancelToken();
        Metadata::setOrderMeta(
            order: $order,
            key: Metadata::KEY_CANCEL_TOKEN,
            value: $cancelToken
        );

        try {
            $payment = $this->createPayment(order: $order);
        } catch (Throwable $e) {
            $this->handleCreatePaymentError(order: $order, error: $e);

            if (
                self::$blockCreateErrorMessage &&
                WooCommerce::isUsingBlocksCheckout()
            ) {
                throw new Exception(
                    message: esc_html(
                        (string)self::$blockCreateErrorMessage
                    )
                );
            }
        }

        if (!isset($payment) || !$payment->isProcessable()) {
            return [
                'result' => 'failure',
                'redirect' => $this->getFailureUrl(order: $order),
            ];
        }

        $this->clearSession();

        Metadata::setPaymentId(order: $order, id: $payment->id);

        // Track this order in session so we can cancel its payment if a new
        // checkout starts on a different order (WC might create a fresh order)
        if (function_exists('WC') && WC()->session !== null) {
            WC()->session->set(self::SESSION_KEY_LAST_RESURS_ORDER, $order->get_id());
        }

        return [
            'result' => 'success',
            'redirect' => $payment->taskRedirectionUrls?->customerUrl ?? $this->getSuccessUrl(
                order: $order
            ),
        ];
    }

    /**
     * Whether payment method is available.
     *
     * @throws ConfigException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function is_available(): bool
    {
        // Is in admin, but in the payment method configuration? Only show the gateway.
        if (
            AdminUtility::isAdmin() &&
            AdminUtility::isTab(tabName: 'checkout')
        ) {
            return false;
        }

        // The conditions below are separated to make debugging easier.

        // Not in checkout? Act like they are all there.
        if (!is_checkout()) {
            return true;
        }

        // If the purchase limit is not fulfilled (or something went south with priceSignage), skip early.
        if (
            $this->validatePurchaseLimit() === false ||
            !$this->canRenderPaymentFields
        ) {
            return false;
        }

        // Validate country, except when restrictions are disabled. This is a
        // credit card-related feature where, in some cases, we want credit cards
        // to work across borders, but they don't when we sell exclusively within
        // our own country. When restrictions are disabled, all payment methods
        // are opened up for cross-border sales. Here, we have chosen not to
        // implement specific checks for which payment methods are allowed to
        // operate in this scenario - it's an all-or-nothing approach.
        if (
            SetMethodCountryRestriction::getData() &&
            !$this->validateCustomerCountry()
        ) {
            return false;
        }

        if (WooCommerce::isUsingBlocksCheckout()) {
            // Always return true for everything coming from blocks checkout since, in this case,
            // filtering on customer types is done in the blocks checkout itself.
            return true;
        }

        $customerType = WcSession::getCustomerType();
        return match ($customerType) {
            CustomerType::LEGAL => ($this->method !== null && $this->method->enabledForLegalCustomer) ?? false,
            CustomerType::NATURAL => ($this->method !== null && $this->method->enabledForNaturalCustomer) ?? false
        };
    }

    /**
     * Customer country validation.
     *
     * @throws ConfigException
     */
    public function validateCustomerCountry(): bool
    {
        // If country restrictions are enabled, we will validate that the customer is located in the
        // same country as the API based country.
        return WC()?->cart && WC()?->customer?->get_billing_country() === WooCommerce::getStoreCountry();
    }

    /**
     * Make sure an answer is returned, even if the values don't exist (when in gateway mode).
     * This protects the storefront against warnings when wrong payment method is trying to validate.
     */
    public function getMinPurchaseLimit(): float
    {
        return ($this->method !== null && $this->method->minPurchaseLimit)
            ? $this->method->minPurchaseLimit
            : 0.0;
    }

    /**
     * Make sure an answer is returned, even if the values don't exist (when in gateway mode).
     * This protects the storefront against warnings when wrong payment method is trying to validate.
     */
    public function getMaxPurchaseLimit(): float
    {
        return ($this->method !== null && $this->method->maxPurchaseLimit)
            ? $this->method->maxPurchaseLimit
            : 0.0;
    }

    /**
     * Admin::isAdmin() won't always work, depending on section of admin panel
     * being viewed. We also check whether the cart exists, as an additional
     * way to check whether we are within the administration panel since there
     * is currently no better way.
     */
    public function isAdmin(): bool
    {
        return AdminUtility::isAdmin() || WC()->cart === null;
    }

    private function generatePaymentFieldsHtml(): void
    {
        try {
            if (AdminUtility::isAdmin() || WC()?->cart === null) {
                return;
            }

            if (!$this->method instanceof PaymentMethod || $this->get_order_total() === 0) {
                return;
            }

            $gatewayHelper = new GatewayHelper(paymentMethod: $this->method);
            $usp = PaymentMethodRepository::getUniqueSellingPoint(
                paymentMethod: $this->method,
                amount: $this->get_order_total()
            );
            $this->uspText = '<div class="rb-usp">' . $usp->getText() . '</div>' . $gatewayHelper->renderPaymentMethodContent(
                paymentMethod: $this->method,
                amount: $this->get_order_total()
            );
        } catch (TranslationException $error) {
            // Translation errors should rather  go as debug messages since we
            // translate with english fallbacks.
            Log::debug(message: $error->getMessage());
        } catch (Throwable $error) {
            Log::error(error: $error);
            $this->canRenderPaymentFields = false;
        }
    }

    /**
     * Make sure payment method is set up properly on null/not null.
     */
    private function resolveNullableMethod(): void
    {
        // Load PaymentMethod from potential order, if not already supplied.
        if ($this->method === null && $this->getOrder() instanceof WC_Order) {
            try {
                $this->method = OrderModule::getPaymentMethod(
                    order: $this->getOrder()
                );
            } catch (Throwable $e) {
                Log::error(error: $e);
            }
        }

        // Override property values with PaymentMethod specific data.
        if ($this->method === null) {
            return;
        }

        $this->id = $this->method->id;
        $this->type = $this->method instanceof PaymentMethod
            ? $this->method->type->value
            : '';
        $this->title = $this->method->name . ($this->isAdmin() ? ' (Resurs Bank)' : '');
        $this->icon = Url::getPaymentMethodIconUrl(type: $this->method->type);
    }

    /**
     * Remove session data related to the checkout process.
     */
    private function clearSession(): void
    {
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

    /**
     * Handle any existing payment before creating a new one.
     *
     * Checks the current order and any previous session order for existing
     * payments. Returns a redirect URL if the customer should continue an
     * existing session, or null to proceed with creating a new payment.
     *
     * @return string|null Redirect URL, or null to proceed with new payment
     */
    private function handleExistingPayment(WC_Order $order): ?string
    {
        // Check current order for existing payment
        $paymentId = Metadata::getOrderMeta(
            order: $order,
            key: Metadata::KEY_PAYMENT_ID
        );

        if ($paymentId !== '') {
            $url = $this->resolveExistingPayment(
                order: $order,
                paymentId: $paymentId,
                redirectIfCompleted: true
            );

            if ($url !== null) {
                return $url;
            }
        }

        // Check previous session order (handles fresh order scenario).
        // WooCommerce may create a fresh order instead of reusing the previous
        // one (e.g., due to cart changes). When this happens, we have Order A
        // (previous, with payment attached) and Order B (current, no payment).
        // Without this check, the previous payment would remain active.
        if (!function_exists('WC') || WC()->session === null) {
            return null;
        }

        $previousOrderId = absint(
            WC()->session->get(self::SESSION_KEY_LAST_RESURS_ORDER) ?? 0
        );

        if ($previousOrderId === 0 || $previousOrderId === $order->get_id()) {
            return null;
        }

        $previousOrder = wc_get_order($previousOrderId);

        if (!$previousOrder instanceof WC_Order) {
            return null;
        }

        Log::debug(
            message: "Checking previous session order $previousOrderId for " .
                "payment to cancel"
        );

        $paymentId = Metadata::getOrderMeta(
            order: $previousOrder,
            key: Metadata::KEY_PAYMENT_ID
        );

        if ($paymentId === '') {
            return null;
        }

        // For previous orders, don't redirect to thank you page if completed
        // (we don't want to redirect to an old order's thank you page)
        return $this->resolveExistingPayment(
            order: $previousOrder,
            paymentId: $paymentId,
            redirectIfCompleted: false
        );
    }

    /**
     * Resolve an existing payment attached to an order.
     *
     * Detaches the payment ID first to prevent REJECTED callbacks (from
     * cancelled payments) from marking the order as Failed. Then checks
     * payment status:
     * - Completed → return thank you URL (if redirectIfCompleted), else null
     * - Active → try to cancel, return session URL if cancel fails
     * - Other status → return null (safe to create new payment)
     *
     * Re-attaches payment ID when returning a URL (customer will complete it).
     *
     * @param WC_Order $order The order with the payment attached
     * @param string $paymentId The payment ID to resolve
     * @param bool $redirectIfCompleted Whether to redirect to thank you page
     *                                  if payment is completed
     * @return string|null Redirect URL, or null to proceed with new payment
     */
    private function resolveExistingPayment(
        WC_Order $order,
        string $paymentId,
        bool $redirectIfCompleted
    ): ?string {
        // Detach payment first - prevents REJECTED callbacks from marking
        // order as Failed as they naturally come when we send a cancel request.
        Metadata::setPaymentId(order: $order, id: '');

        Log::debug(
            message: "Detached payment $paymentId from order {$order->get_id()}, " .
                "checking status"
        );

        $payment = PaymentRepository::get(paymentId: $paymentId);

        // Payment completed - redirect to thank you or proceed based on flag
        if (in_array($payment->status, [PaymentStatus::FROZEN, PaymentStatus::ACCEPTED], true)) {
            Log::debug(
                message: "Payment $paymentId is {$payment->status->value}, " .
                    "checkout completed"
            );

            // Re-attach so callbacks still work for this order
            Metadata::setPaymentId(order: $order, id: $paymentId);

            if ($redirectIfCompleted) {
                return $this->getSuccessUrl(order: $order);
            }

            return null;
        }

        // Payment not active - safe to proceed with new payment
        if ($payment->status !== PaymentStatus::TASK_REDIRECTION_REQUIRED) {
            Log::debug(
                message: "Payment $paymentId is {$payment->status->value}, " .
                    "not active - proceeding"
            );
            return null;
        }

        // Try to cancel the active payment session
        try {
            // Invalidate the cancel token so the failure redirect from this
            // cancelled payment won't cancel the order. This is needed for the
            // "fresh order" scenario where WC created a new order and we're
            // cancelling the previous order's payment - without this, the old
            // failure URL would still match the old token.
            Metadata::setOrderMeta(
                order: $order,
                key: Metadata::KEY_CANCEL_TOKEN,
                value: $this->generateCancelToken()
            );

            PaymentRepository::cancel(paymentId: $paymentId);
            Log::debug(message: "Cancelled payment $paymentId");

            return null;
        } catch (Throwable $e) {
            // Cancel failed - payment is in progress, redirect to existing session
            Log::debug(
                message: "Cancel failed for $paymentId: {$e->getMessage()} - " .
                    "redirecting to existing session"
            );

            Metadata::setPaymentId(order: $order, id: $paymentId);
            return $this->getExistingSessionUrl(paymentId: $paymentId);
        }
    }

    /**
     * Get the gateway URL for an existing payment session.
     *
     * Used when cancellation fails because payment is in progress - we redirect
     * the customer back to their existing session instead of creating a new one.
     *
     * @throws Exception When the URL cannot be retrieved (API error).
     */
    private function getExistingSessionUrl(string $paymentId): string
    {
        try {
            $taskStatus = PaymentRepository::getTaskStatusDetails(
                paymentId: $paymentId
            );

            if ($taskStatus->customer !== null) {
                Log::debug(
                    message: "Retrieved existing session URL for payment $paymentId"
                );
                return $taskStatus->customer->customerUrl;
            }
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: "Failed to get taskStatusDetails for $paymentId"
            );
        }

        // Could not get the redirect URL - show error to customer
        throw new Exception(
            message: Translator::translate(phraseId: 'api-temporary-error')
        );
    }

    /**
     * Create a new payment at Resurs Bank.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalCharsetException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @throws AttributeCombinationException
     */
    private function createPayment(
        WC_Order $order
    ): Payment {
        if ($this->method === null) {
            throw new IllegalValueException(
                message: 'Cannot proceed without Resurs Bank payment method.'
            );
        }

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        $order->add_order_note('Resurs initiated payment process.');
        Metadata::setOrderMeta(
            order: $order,
            key: Metadata::KEY_REPOSITORY_CREATED,
            value: (string)time()
        );

        return PaymentRepository::create(
            paymentMethodId: $this->method->id,
            orderLines: Order::getOrderLines(order: $order),
            orderReference: (string)$order->get_id(),
            customer: Customer::getCustomer(order: $order),
            //Customer::getLoggedInCustomerIdMeta(order: $order),
            metadata: $this->getBaseMetadata(
                order: $order
            ),
            options: $this->getOptions(order: $order)
        );
    }

    /**
     * Get metadata to attach to order.
     *
     * @throws AttributeCombinationException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     */
    private function getBaseMetadata(WC_Order $order): Payment\Metadata
    {
        $platformInformation = PaymentRepository::getIntegrationInfoMetadata(
            platform: 'WooCommerce',
            platformVersion: UserAgent::getWooCommerceVersion(),
            pluginVersion: UserAgent::getPluginVersion()
        );
        $data = $platformInformation->custom->toArray();

        if ($order->get_user_id() > 0) {
            try {
                $data[] = Customer::getLoggedInCustomerIdMetaEntry(
                    order: $order
                );
            } catch (IllegalValueException $error) {
                Log::error(error: $error);
            }
        }

        return new Payment\Metadata(
            custom: new Payment\Metadata\EntryCollection(data: $data)
        );
    }

    /**
     * Method to properly fetch an order if it is present on a current screen (the order view), making sure we
     * can display "Payment via <method>" instead of "Payment via <uuid>".
     *
     */
    private function getOrder(): ?WC_Order
    {
        global $theorder;

        // Non-HPOS mode (if order is already present).
        if ($theorder instanceof WC_Order) {
            return $theorder;
        }

        // HPOS quick mode.
        $wcOrder = wc_get_order();

        if ($wcOrder instanceof WC_Order) {
            return $wcOrder;
        }

        // Legacy order objects by post/id.
        $orderIdByRequest = WordPress::getQueryParam('id');

        if ($orderIdByRequest === '') {
            $postId = WordPress::getQueryParam('post');

            if ($postId !== '' && (int)$postId > 0) {
                /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
                $testOrderByPost = wc_get_order((int)$postId);

                if ($testOrderByPost instanceof WC_Order) {
                    $orderIdByRequest = (string)$testOrderByPost->get_id();
                }
            }
        }

        // Validate that we have a proper order by first requesting it. Since we still get booleans in
        // for example a bulk editing view, the order has to be validated before proceeding to the return.

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        $validatedOrder = $orderIdByRequest !== ''
            ? wc_get_order((int)$orderIdByRequest)
            : false;

        // Return the order if valid ID is provided and it's a valid order.
        return $validatedOrder instanceof WC_Order ? $validatedOrder : null;
    }

    /**
     * Get URL to success page.
     */
    private function getSuccessUrl(WC_Order $order): string
    {
        return $this->get_return_url(order: $order);
    }

    /**
     * Get URL to failure page with cancel token appended.
     *
     * The cancel token is used by the failure page to verify that this failure
     * redirect corresponds to the current payment attempt. If tokens don't match,
     * it means a new payment was created and the order should not be cancelled.
     */
    private function getFailureUrl(WC_Order $order): string
    {
        $baseUrl = html_entity_decode(
            string: $order->get_cancel_order_url()
        );

        $cancelToken = Metadata::getOrderMeta(
            order: $order,
            key: Metadata::KEY_CANCEL_TOKEN
        );

        if ($cancelToken === '') {
            return $baseUrl;
        }

        return add_query_arg('rb_cancel_token', $cancelToken, $baseUrl);
    }

    /**
     * Generate a unique token for identifying this payment attempt.
     *
     * Used to prevent the failure page from cancelling the order when a payment
     * has been superseded by a new one (e.g., multi-tab checkout).
     */
    private function generateCancelToken(): string
    {
        return substr(md5((string)microtime(true) . wp_generate_uuid4()), 0, 16);
    }

    /**
     * Attempts to extract and translate a more detailed error message from
     * CurlException.
     */
    private function handleCreatePaymentError(WC_Order $order, Throwable $error): void
    {
        Log::error(
            error: $error,
            message: $error->getMessage()
        );

        $translatedMessage = Translator::translate(
            phraseId: 'error-creating-payment'
        );

        /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
        $order->add_order_note($translatedMessage);

        $finalMessage = $translatedMessage;

        if ($error instanceof CurlException) {
            try {
                // getDetailedMessage already appends details when applicable
                $finalMessage = $error->getDetailedMessage(
                    msg: $translatedMessage
                );

                Log::error(error: $error, message: $finalMessage);
            } catch (ConfigException $configException) {
                // Do not break checkout on config issues, but log them
                Log::error(error: $configException);
            }
        }

        // Escape message for output to user via wc_add_notice
        wc_add_notice(esc_html($finalMessage), 'error');

        // Pass escaped message to process_payment() for Blocks checkout
        self::$blockCreateErrorMessage = esc_html($finalMessage);
    }

    /**
     * @throws AttributeCombinationException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     */
    private function getOptions(WC_Order $order): Options
    {
        // TTL default from WooCommerce. If stock reservations is enabled and over 0, we should use that value instead
        // of our default.
        $stockEnabled = ((string)get_option(
            'woocommerce_manage_stock'
        ) === 'yes');
        $holdStockMinutes = (int)get_option('woocommerce_hold_stock_minutes');

        return new Options(
            initiatedOnCustomersDevice: true,
            handleManualInspection: false,
            handleFrozenPayments: true,
            redirectionUrls: new RedirectionUrls(
                customer: new ParticipantRedirectionUrls(
                    failUrl: $this->getFailureUrl(order: $order),
                    successUrl: $this->getSuccessUrl(order: $order)
                ),
                coApplicant: null,
                merchant: null
            ),
            callbacks: new Callbacks(
                authorization: new Callback(
                    url: Url::getCallbackUrl(type: CallbackType::AUTHORIZATION)
                ),
                management: new Callback(
                    url: Url::getCallbackUrl(type: CallbackType::MANAGEMENT)
                ),
                creditApplication: null
            ),
            timeToLiveInMinutes: $stockEnabled &&
            $holdStockMinutes > 0 &&
            $holdStockMinutes <= 43200 ? $holdStockMinutes : 120
        );
    }

    /**
     * Whether total amount of order / cart is within min / max purchase limit.
     */
    private function validatePurchaseLimit(): bool
    {
        $total = 0.0;

        /* We need to confirm that we have a cart with a total before validating the totals with
            the allowed amount in the payment method. */
        if (WC()->cart instanceof WC_Cart) {
            // Primary way to fetch totals.
            $total = (float)$this->get_order_total();

            // The prior data fetched through get_order_total and/or order-pay (get_query_var) for some reason
            // is only returning 0, even if there is a final order total to compare purchase limits with.
            // As it seems, the subtotal is the best option there is, and is fetched from the active cart.
            $totals = WC()->cart->get_totals();

            if (
                $total === 0.0 &&
                isset($totals['total']) &&
                is_array(value: $totals) &&
                (float)$totals['total'] > 0
            ) {
                $total = (float)$totals['total'];
            }
        }

        return
            $total >= $this->getMinPurchaseLimit() &&
            $total <= $this->getMaxPurchaseLimit();
    }
}
