<?php

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
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Order;
use WC_Payment_Gateway;
use function get_option;

/**
 * This class represents a Resurs Bank payment method in WooCommerce.
 *
 * In WooCommerce, a payment method is actually a gateway. A separate instance
 * of this class will therefore be created for each individual Resurs Bank
 * payment method.
 */
class Resursbank extends WC_Payment_Gateway
{
    public function __construct(
        private readonly PaymentMethod $method
    ) {
        $this->id = $method->id;
        $this->plugin_id = 'resursbank-mapi';
        $this->title = $method->name . ' (Resurs Bank)';
        $this->method_title = $this->title;
        $this->method_description = 'Resurs Bank Gateway Method';
        $this->icon = Url::getPaymentMethodIconUrl(type: $method->type);
        $this->has_fields =  true;
        $this->enabled = 'yes';
    }

    /**
     * Render info about our payment methods in their section at checkout.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function payment_fields(): void
    {
        try {
            $gatewayHelper = new GatewayHelper(
                paymentMethod: $this->method,
                amount: $this->get_order_total()
            );

            echo $gatewayHelper->getUspWidget() .
                '<div class="payment-method-content">' .
                    $gatewayHelper->getCostList() .
                    $gatewayHelper->getReadMore() .
                    $gatewayHelper->getPriceSignageWarning() .
                '</div>';
        } catch (TranslationException $error) {
            // Translation errors should rather go as debug messages since we
            // translate with english fallbacks.
            Log::debug(message: $error->getMessage());
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
    }

    /**
     * Create Resurs Bank payment and assign additional metadata to WC_Order.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     * @throws Exception
     */
    public function process_payment(mixed $order_id): array
    {
        global $blockCreateErrorMessage;

        $order = new WC_Order(order: $order_id);

        try {
            $payment = $this->createPayment(order: $order);
        } catch (Throwable $e) {
            $this->handleCreatePaymentError(error: $e);
            if ($blockCreateErrorMessage && WooCommerce::isUsingBlocksCheckout()) {
                throw new Exception(message: $blockCreateErrorMessage);
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

        return [
            'result' => 'success',
            'redirect' => $payment->taskRedirectionUrls?->customerUrl ?? $this->getSuccessUrl(
                    order: $order
                ),
        ];
    }

    /**
     * Remove session data related to the checkout process.
     */
    private function clearSession(): void
    {
        try {
            Repository::clearSsnData();
        } catch (ConfigException $e) {
            Log::error(error: $e);
        }
    }

    /**
     * @param WC_Order $order
     * @return Payment
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
        return PaymentRepository::create(
            paymentMethodId: $this->method->id,
            orderLines: Order::getOrderLines(order: $order),
            orderReference: (string)$order->get_id(),
            customer: Customer::getCustomer(order: $order),
            metadata: $this->getBaseMetadata(order: $order), //Customer::getLoggedInCustomerIdMeta(order: $order),
            options: $this->getOptions(order: $order)
        );
    }

    /**
     * Get metadata to attach to order.
     *
     * @param WC_Order $order
     *
     * @return Payment\Metadata
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
                $data[] = Customer::getLoggedInCustomerIdMetaEntry(order: $order);
            } catch (IllegalValueException $error) {
                Log::error(error: $error);
            }
        }

        return new Payment\Metadata(
            custom: new Payment\Metadata\EntryCollection(data: $data)
        );
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
    // @phpcs:ignoreFile CognitiveComplexity
    private function handleCreatePaymentError(Throwable $error): void
    {
        global $blockCreateErrorMessage;

        try {
            if ($error instanceof CurlException) {
                if (count(value: $error->getDetails())) {
                    /** @var $detail */
                    foreach ($error->getDetails() as $detail) {
                        MessageBag::addError(message: $detail);
                        $blockCreateErrorMessage .= $detail . "\n";
                    }
                } else {
                    MessageBag::addError(message: $error->getMessage());
                    $blockCreateErrorMessage = $error->getMessage();
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
     * @param WC_Order $order
     * @return Options
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
}
