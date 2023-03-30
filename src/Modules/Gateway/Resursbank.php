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
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;
use WC_Cart;
use WC_Order;
use WC_Payment_Gateway;

/**
 * Resurs Bank payment gateway.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Resursbank extends WC_Payment_Gateway
{
    /**
     * Setup.
     */
    public function __construct(
        private ?PaymentMethod $method = null
    ) {
        // Assign default property values for this gateway.
        $this->id = RESURSBANK_MODULE_PREFIX;
        $this->title = 'Resurs Bank';
        $this->method_description = 'Resurs Bank Gateway';
        $this->has_fields = true;
        $this->enabled = 'yes';

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
            $this->title = $this->method->name;
            $this->icon = Url::getPaymentMethodIconUrl(
                type: $this->method->type
            );
        }

        // Mirror title to method_title.
        $this->method_title = $this->title;
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

        // @todo customerUrl can be empty, so redirect can technically become empty, not sure if it matters.
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
        if ($this->method === null) {
            return true;
        }

        return $this->validatePurchaseLimit() && match (WcSession::getCustomerType()) {
            CustomerType::LEGAL => $this->method->enabledForLegalCustomer,
            CustomerType::NATURAL => $this->method->enabledForNaturalCustomer
        };
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
                wc_add_notice(message: $error->getMessage(), notice_type: 'error');
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
        // @todo Defaults like manual inspection, frozen payments, etc should be changed to configurable options
        // @todo through the admin panel.
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
            timeToLiveInMinutes: 120
        );
    }

    /**
     * Whether total amount of order / cart is within min / max purchase limit.
     */
    private function validatePurchaseLimit(): bool
    {
        $total = 0.0;

        /* We need to confirm we can resolve order / cart total manually,
           otherwise calling $this->>get_order_total() can cause an error. */
        if (
            WC()->cart instanceof WC_Cart ||
            (int) absint(maybeint: get_query_var(query_var: 'order-pay')) > 0
        ) {
            $total = (float) $this->get_order_total();
        }

        return
            $total >= $this->method->minPurchaseLimit &&
            $total <= $this->method->maxPurchaseLimit
        ;
    }
}
