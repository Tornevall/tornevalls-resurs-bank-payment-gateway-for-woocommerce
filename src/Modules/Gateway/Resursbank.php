<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
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
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Admin as AdminUtility;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;

use function get_option;

/**
 * Resurs Bank payment gateway.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Resursbank extends WC_Payment_Gateway
{
    public ?string $type = '';

    /**
     * Setup.
     */
    public function __construct(
        private ?PaymentMethod $method = null
    ) {
        // Assign default property values for this gateway.
        $this->id = RESURSBANK_MODULE_PREFIX;
        $this->plugin_id = 'resursbank-mapi';
        $this->title = 'Resurs Bank';
        $this->method_description = 'Resurs Bank Gateway';
        $this->has_fields = true;
        $this->enabled = 'yes';
        $this->type = null;

        // Load PaymentMethod from potential order, if not already supplied.
        if ($this->method === null) {
            try {
                $this->method = OrderModule::getPaymentMethod(
                    order: $this->getOrder()
                );
            } catch (Throwable $e) {
                Log::error(error: $e);
            }
        }

        // Override property values with PaymentMethod specific data.
        if ($this->method !== null) {
            $this->id = $this->method->id;
            $this->type = $this->method instanceof PaymentMethod
                ? $this->method->type->value
                : '';
            $this->title = $this->method->name . ($this->isAdmin() ? ' (Resurs Bank)' : '');
            $this->icon = Url::getPaymentMethodIconUrl(
                type: $this->method->type
            );
        }

        // Mirror title to method_title.
        $this->method_title = $this->title;
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
     */
    public function payment_fields(): void
    {
        try {
            $usp = PaymentMethodRepository::getUniqueSellingPoint(
                paymentMethod: $this->method,
                amount: $this->get_order_total()
            );
            echo $usp->content;
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Create Resurs Bank payment and assign additional metadata to WC_Order.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function process_payment(mixed $order_id): array
    {
        $order = new WC_Order(order: $order_id);

        try {
            $payment = $this->createPayment(order: $order);
        } catch (Throwable $e) {
            $this->handleCreatePaymentError(order: $order, error: $e);
        }

        if (!isset($payment) || !$payment->isProcessable()) {
            return [
                'result' => 'failure',
                'redirect' => $this->getFailureUrl(order: $order),
            ];
        }

        $this->clearSession();

        Metadata::setPaymentId(order: $order, id: $payment->id);

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

        // Not in checkout? Act like they are all there.
        if (!is_checkout()) {
            return true;
        }

        return $this->validatePurchaseLimit() && match (WcSession::getCustomerType()) {
                CustomerType::LEGAL => ($this->method !== null && $this->method->enabledForLegalCustomer) ?? false,
                CustomerType::NATURAL => ($this->method !== null && $this->method->enabledForNaturalCustomer) ?? false
        };
    }

    /**
     * Make sure an answer is returned, even if the values don't exist (when in gateway mode).
     * This protects the storefront against warnings when wrong payment method is trying to validate.
     */
    public function getMinPurchaseLimit(): float
    {
        return $this->method->minPurchaseLimit ?? 0.0;
    }

    /**
     * Make sure an answer is returned, even if the values don't exist (when in gateway mode).
     * This protects the storefront against warnings when wrong payment method is trying to validate.
     */
    public function getMaxPurchaseLimit(): float
    {
        return $this->method->maxPurchaseLimit ?? 0.0;
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
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalCharsetException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws FilesystemException
     * @throws TranslationException
     */
    private function createPayment(
        WC_Order $order
    ): Payment {
        if ($this->method === null) {
            throw new IllegalValueException(
                message: 'Cannot proceed without Resurs Bank payment method.'
            );
        }

        return PaymentRepository::create(
            storeId: StoreId::getData(),
            paymentMethodId: $this->method->id,
            orderLines: Order::getOrderLines(order: $order),
            orderReference: (string)$order->get_id(),
            customer: Customer::getCustomer(order: $order),
            metadata: Customer::getLoggedInCustomerIdMeta(order: $order),
            options: $this->getOptions(order: $order)
        );
    }

    /**
     * Method to properly fetch an order if it is present on a current screen (the order view), making sure we
     * can display "Payment via <method>" instead of "Payment via <uuid>".
     *
     * @noinspection SpellCheckingInspection
     */
    private function getOrder(): WC_Order
    {
        global $theorder;

        if ($theorder instanceof WC_Order) {
            return $theorder;
        }

        return new WC_Order(order: $_GET['post'] ?? null);
    }

    /**
     * Get URL to success page.
     */
    private function getSuccessUrl(WC_Order $order): string
    {
        return $this->get_return_url(order: $order);
    }

    /**
     * Get URL to failure page.
     */
    private function getFailureUrl(WC_Order $order): string
    {
        return html_entity_decode(
            string: $order->get_cancel_order_url()
        );
    }

    /**
     * Attempts to extract and translate more detailed error message from
     * CurlException.
     */
    private function handleCreatePaymentError(WC_Order $order, Throwable $error): void
    {
        Log::error(
            error: $error,
            message: Translator::translate(phraseId: 'error-creating-payment')
        );

        try {
            $order->add_order_note(
                note: Translator::translate(phraseId: 'error-creating-payment')
            );

            if ($error instanceof CurlException) {
                foreach ($error->getDetails() as $detail) {
                    MessageBag::addError(message: $detail);
                }
            } else {
                // Only display relevant error messages on the order placement screen. CurlExceptions usually contains
                // trace messages for which we do not need to show in the customer view.
                wc_add_notice(
                    message: $error->getMessage(),
                    notice_type: 'error'
                );
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * @throws IllegalValueException
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
                )
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
