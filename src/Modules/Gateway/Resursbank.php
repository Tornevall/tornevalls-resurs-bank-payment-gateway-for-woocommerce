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
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\Callback;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\Callbacks;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\ParticipantRedirectionUrls;
use Resursbank\Ecom\Lib\Model\Payment\CreatePaymentRequest\Options\RedirectionUrls;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\Entry;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\RouteVariant;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\UserAgent;
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
        public readonly PaymentMethod $method
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
     * NOTE: This is only used in legacy checkout, blocks render the checkout
     * using React components instead.
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
        } catch (Throwable $error) {
            Logger::error(message: $error);
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
        $order = new WC_Order(order: $order_id);

        // Add customer id to metadata, if customer is logged in.
        if ($order->get_user_id() > 0) {
            $meta[] = new Entry(
                key: 'externalCustomerId',
                value: (string) $order->get_user_id()
            );
        }

        try {
            $payment = PaymentRepository::create(
                paymentMethodId: $this->method->id,
                orderLines: Order::getOrderLines(order: $order),
                orderReference: (string)$order->get_id(),
                customer: Customer::getCustomer(order: $order),
                metadata: PaymentRepository::getIntegrationInfoMetadata(
                    platform: 'WooCommerce',
                    platformVersion: UserAgent::getWooCommerceVersion(),
                    pluginVersion: UserAgent::getPluginVersion(),
                    additionalData: $meta ?? []
                ),
                options: $this->getOptions(order: $order)
            );
        } catch (CurlException $error) {
            throw new HttpException(
                message: $error->getDetailedMessage(
                    msg: Translator::translate(phraseId: 'payment-create-failed')
                ),
            );
        }

        // Get URL to redirect customer to (gateway URL at Resurs Bank).
        $redirectUrl = $payment->taskRedirectionUrls?->customerUrl;

        // When we create a payment, we will always be asked to redirect to the
        // gateway for confirmation of the payment. If we do not get a URL to
        // redirect to, something went wrong, and we will treat it as a failure.
        if (!$redirectUrl) {
            return [
                'result' => 'failure',
                'redirect' => $this->getFailureUrl(order: $order),
            ];
        }

        // Clear SSN data from session after payment creation.
        try {
            Repository::clearSsnData();
        } catch (ConfigException $e) {
            Logger::error(message: $e);
        }

        // Store payment id in order metadata for future reference.
        //
        // Note that this is metadata from the WooCommerce plugin, not the
        // Resurs Bank payment metadata.
        Metadata::setPaymentId(order: $order, id: $payment->id);

        // Redirect customer to Resurs Bank payment page.
        return [
            'result' => 'success',
            'redirect' => $redirectUrl
        ];
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
     * @param WC_Order $order
     * @return Options
     * @throws AttributeCombinationException
     * @throws HttpException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws UserSettingsException
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
                    url: Route::getUrl(route: RouteVariant::AuthorizationCallback)
                ),
                management: null,
                creditApplication: null
            ),
            timeToLiveInMinutes: $stockEnabled &&
            $holdStockMinutes > 0 &&
            $holdStockMinutes <= 43200 ? $holdStockMinutes : 120
        );
    }
}
