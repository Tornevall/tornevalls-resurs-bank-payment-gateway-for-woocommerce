<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */

/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecommerce\Types\CheckoutType;
use ResursBank\Gateway\ResursDefault;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use RuntimeException;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\Utils\WordPress as wpHelper;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Refund;
use WC_Product;
use WC_Tax;
use function count;
use function in_array;
use function is_array;

/**
 * Class Plugin Internal plugin handler.
 *
 * @package ResursBank\Module
 */
class PluginHooks
{
    public function __construct()
    {
        add_filter('rbwc_js_loaders_checkout', [$this, 'getRcoLoaderScripts']);
        add_filter('rbwc_get_payment_method_icon', [$this, 'getMethodIconByContent'], 10, 2);
        add_filter('rbwc_part_payment_string', [$this, 'getPartPaymentWidgetPage'], 10, 2);
        add_filter('rbwc_get_order_note_prefix', [$this, 'getDefaultOrderNotePrefix'], 1);
        add_action('rbwc_mock_update_payment_reference_failure', [$this, 'mockUpdatePaymentFailure']);
        add_action('rbwc_mock_create_iframe_exception', [$this, 'mockCreateIframeException']);
        add_action('rbwc_mock_callback_update_exception', [$this, 'mockCallbackUpdateException']);
        add_action('rbwc_mock_get_payment_methods_exception', [$this, 'mockGetPaymentMethodsException']);
        add_action('rbwc_mock_annuity_factor_config_exception', [$this, 'mockAnnuityFactorConfigException']);
        add_action('rbwc_mock_empty_price_info_html', [$this, 'mockEmptyPriceInfoHtml']);
        add_action('mock_update_callback_exception', [$this, 'mockUpdateCallbackException']);
        add_action('mock_refund_exception', [$this, 'mockRefundException']);
        add_filter('resursbank_temporary_disable_checkout', [$this, 'setRcoDisabledWarning'], 99999, 1);
        add_filter('rbwc_get_available_auto_debit_methods', [$this, 'getAvailableAutoDebitMethods']);
        add_action('rbwc_update_order_status_by_queue', [$this, 'updateOrderStatusByQueue'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'updateOrderStatusByWooCommerce'], 10, 3);
        add_action('woocommerce_order_refunded', [$this, 'refundResursOrder'], 10, 2);
        add_action('woocommerce_ajax_order_items_removed', [$this, 'removeOrderItemFromResurs'], 10, 4);
        add_action('rbwc_get_tax_classes', [$this, 'getTaxClasses']);
        add_action('rbwc_get_custom_form_fields', [$this, 'getCustomFormFields'], 10, 2);
        add_action('rbwc_get_support_address_list', [$this, 'getSupportAddressList'], 10, 2);
    }

    /**
     * Moving specific form fields over to another tab of Resurs Bank.
     * This function is mostly used as an example of the filters that can be used.
     *
     * @param $formFields
     * @param $section
     * @return mixed
     * @since 0.0.1.6
     */
    public function getCustomFormFields($formFields, $section)
    {
        $customPaymentMethodFields = WordPress::applyFilters(
            'paymentMethodsOnFirstPage',
            Data::getResursOption('payment_methods_on_first_page')
        );
        if ($customPaymentMethodFields) {
            $array = new Arrays();

            // Push the first array straight into the basic form.
            $formFields['basic']['payment_methods_list'] = $formFields['payment_methods']['payment_methods_list'];
            $formFields['basic'] = $array->moveArrayAfter(
                $formFields['basic'],
                $formFields['payment_methods']['payment_methods_button'],
                'payment_methods_list',
                'payment_methods_button'
            );
            $formFields['basic'] = $array->moveArrayAfter(
                $formFields['basic'],
                $formFields['payment_methods']['callbacks_list'],
                'payment_methods_button',
                'callbacks_list'
            );
            $formFields['basic'] = $array->moveArrayAfter(
                $formFields['basic'],
                $formFields['payment_methods']['callbacks_button'],
                'callbacks_list',
                'callbacks_button'
            );
            $formFields['basic'] = $array->moveArrayAfter(
                $formFields['basic'],
                $formFields['payment_methods']['trigger_callback_button'],
                'callbacks_button',
                'trigger_callback_button'
            );

            unset(
                $formFields['payment_methods']['payment_methods_list'],
                $formFields['payment_methods']['payment_methods_button'],
                $formFields['payment_methods']['callbacks_list'],
                $formFields['payment_methods']['callbacks_button'],
                $formFields['payment_methods']['trigger_callback_button'],
                $formFields['payment_methods']['accept_rejected_callbacks']
            );
        }

        return $formFields;
    }

    /**
     * @param array $addressList
     * @return array
     * @since 0.0.1.6
     */
    public function getSupportAddressList($addressList): array
    {
        if (Data::isTest()) {
            $addressList['Resurs Bank (Staging Support)'] = $this->getContactEnvironmentString(
                'test',
                __(
                    '(Selected Environment: Test - Suggested)',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                )
            );
            $addressList['Resurs Bank (Production Support)'] = $this->getContactEnvironmentString('production', '');
        } else {
            $addressList['Resurs Bank (Production Support)'] = $this->getContactEnvironmentString(
                'production',
                __(
                    '(Selected Environment: Production - Suggested)',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                )
            );
            $addressList['Resurs Bank (Staging Support)'] = $this->getContactEnvironmentString('test', '');
        }
        if (Data::isOriginalCodeBase()) {
            $addressList['Tornevall Plugin Issues'] = [
                'info' => __(
                    'Plugin related questions and things that is not related to Resurs Bank.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                'mail' => 'support@tornevall.net',
            ];
        }

        return (array)$addressList;
    }

    /**
     * @param $type
     * @param $preferredString
     * @return array
     * @since 0.0.1.6
     */
    private function getContactEnvironmentString($type, $preferredString): array
    {
        if ($type === 'test') {
            $return = [
                'info' => sprintf(
                    'Matters related to Resurs Bank in staging and test environments %s',
                    $preferredString
                ),
                'mail' => 'onboarding@resurs.se',
            ];
        } else {
            $return = [
                'info' => sprintf(
                    'Maters related to Resurs Bank in live production environments %s',
                    $preferredString
                ),
                'mail' => 'support@resurs.se',
            ];
        }

        return $return;
    }

    /**
     * Tax class renderer for configuration.
     *
     * @return array
     * @since 0.0.1.5
     */
    public function getTaxClasses(): array
    {
        $return = [];
        $taxClasses = WC_Tax::get_tax_classes();
        if (!in_array('', $taxClasses, true)) { // Make sure "Standard rate" (empty class name) is present.
            array_unshift($taxClasses, 'Standard');
        }
        foreach ($taxClasses as $taxRateIndex => $taxClass) { // For each tax class, get all rates.
            $rates = WC_Tax::get_rates_for_tax_class($taxClass);
            if (count($rates)) {
                if ($taxRateIndex === 0) {
                    $taxRateName = 'standard';
                    $return[$taxRateName] = $taxClass;
                } else {
                    foreach ($rates as $rate) {
                        $taxRateName = $rate->tax_rate_class;
                        $return[$taxRateName] = $taxClass;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Pre-annulment feature for order, when orders are not in its proper state.
     *
     * @param $itemId
     * @param $item
     * @param $changedStore
     * @param $order
     * @since 0.0.1.0
     */
    public function removeOrderItemFromResurs($itemId, $item, $changedStore, $order)
    {
        // Currently unsupported.
    }

    /**
     * @param $orderId
     * @param $oldSlug
     * @param $newSlug
     * @throws Exception
     * @since 0.0.1.0
     */
    public function updateOrderStatusByWooCommerce($orderId, $oldSlug, $newSlug)
    {
        /** @var array $order */
        $order = Data::getResursOrderIfExists($orderId);
        if (!empty($order) && isset($order['ecom'])) {
            Data::canLog(
                Data::CAN_LOG_ORDER_EVENTS,
                sprintf(
                    '%s: orderId=%s, oldSlug=%s, newSlug=%s',
                    __FUNCTION__,
                    $orderId,
                    $oldSlug,
                    $newSlug
                )
            );

            // Pre check old status. There are some statuses that prevents editing.
            // Send the full order object here, not just WC_Order.
            $this->handleOrderStatusByOldSlug($oldSlug, $order);

            // This is where we handle order statuses changed from WooCommerce.
            // Send the full order object here, not just WC_Order.
            $this->handleOrderByNewSlug($newSlug, $order);
        }
    }

    /**
     * Handle old slugs first.
     * Equivalent to RB v2.x method order_status_changed first parts.
     *
     * @param $oldSlug
     * @param $order
     * @since 0.0.1.0
     */
    private function handleOrderStatusByOldSlug($oldSlug, $order)
    {
        $url = admin_url('post.php');
        $url = add_query_arg('post', $order['order']->get_id(), $url);
        $url = add_query_arg('action', 'edit', $url);

        $ecomStatus = isset($order['ecom']->status) ? (array)$order['ecom']->status : [];

        switch ($oldSlug) {
            case 'cancelled':
                if (in_array('IS_ANNULLED', $ecomStatus, true)) {
                    wp_set_object_terms($order['order']->get_id(), $oldSlug, 'shop_order_status', false);
                    wp_safe_redirect($url);
                }
                break;
            case 'refunded':
                if (in_array('IS_CREDITED', $ecomStatus, true)) {
                    wp_set_object_terms($order['order']->get_id(), $oldSlug, 'shop_order_status', false);
                    wp_safe_redirect($url);
                }
                break;
            default:
        }
    }

    /**
     * If an order is not creditable, not debited, not credited and not annulled it is still an order
     * that can be handled as a "full unhandled order".
     *
     * @param $paymentId
     * @return bool
     * @since 0.0.1.0
     */
    private function isFullAfterShopRequest($paymentId, $connection): bool
    {
        return (
            !$connection->canCredit($paymentId) &&
            !$connection->getIsDebited($paymentId) &&
            !$connection->getIsCredited($paymentId) &&
            !$connection->getIsAnnulled($paymentId)
        );
    }

    /**
     * Equivalent to v2's getOrderRowsByRefundedDiscountItems, but split up to get better overview.
     *
     * @param WC_Order|WC_Order_Refund $order
     * @param ResursBank $connection
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection LongLine
     */
    private function hasCustomAfterShopOrderLines($order, $connection): bool
    {
        $return = false;

        if ($order instanceof WC_Order || $order instanceof WC_Order_Refund) {
            $return = $this->hasCustomAfterShopDiscount($order, $connection);

            // The method check below is equivalent with a dry run which contained a check for which $return
            // was true if there is a custom discount or shipping applied. Basically, the if condition from the
            // below url, is: "If dry run confirms shipping, and request is not based on "full aftershop" or
            // if $return from discount control is already true.

            // See https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce/src/3f459baaee33d2170b198404267adb1acca96372/resursbankmain.php#lines-4417
            if ($this->hasCustomAfterShopShipping($order)) {
                $this->addShippingOrderLine(
                    $this->getPositiveValueFromNegative($order->get_shipping_tax()),
                    $this->getPositiveValueFromNegative($order->get_shipping_total()),
                    $connection
                );
            }
        }

        return $return;
    }

    /**
     * @param $valueString
     * @return float
     * @since 0.0.1.0
     */
    private function getPositiveValueFromNegative($valueString): float
    {
        return (float)preg_replace('/^-/', '', $valueString);
    }

    /**
     * @param $shippingTax
     * @param $shippingTotal
     * @since 0.0.1.0
     */
    private function addShippingOrderLine($shippingTax, $shippingTotal, $connection)
    {
        $shipping_tax_pct = (!is_nan(round($shippingTax / $shippingTotal, 2) * 100) ?
            @round($shippingTax / $shippingTotal, 2) * 100 : 0);

        $connection->addOrderLine(
            WordPress::applyFilters('getShippingName', 'shipping'),
            WordPress::applyFilters(
                'getShippingDescription',
                __('Shipping', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce')
            ),
            preg_replace('/^-/', '', $shippingTotal),
            $shipping_tax_pct,
            'st',
            'SHIPPING_FEE',
            1
        );
    }

    /**
     * Equivalent to v2's getOrderRowsByRefundedDiscountItems - the discounts. This is the most
     * from v2 untouched codebase that will ever be found in this plugin again. It very much contains all
     * we need to resolve discounts within an aftershop request. Dead variables and some other things
     * are however, from that version, cleaned up.
     *
     * @param WC_Order $order
     * @param ResursBank $connection
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    private function hasCustomAfterShopDiscount($order, $connection): bool
    {
        $return = false;

        // TODO: Store this information as metadata instead so each order gets handled
        // TODO: properly in aftershop mode.
        $discardCouponVat = (bool)Data::getResursOption('discard_coupon_vat');

        if ($order instanceof WC_Order) {
            $discountTotal = (float)$order->get_discount_total();
            if ($discountTotal) {
                $orderItems = $order->get_items();
                /** @var $item WC_Order_Item_Product */
                foreach ($orderItems as $item) {
                    $product = new WC_Product($item->get_product_id());
                    $orderItemQuantity = $item->get_quantity();
                    $refundedQuantity = $order->get_qty_refunded_for_item($item->get_id());
                    $rowsLeftToHandle = $orderItemQuantity + $refundedQuantity;
                    $itemQuantity = preg_replace('/^-/', '', $item->get_quantity());
                    $articleId = WooCommerce::getProperArticleNumber($product);
                    $amountPct = !is_nan(
                        @round($item->get_total_tax() / $item->get_total(), 2) * 100
                    ) ? @round($item->get_total_tax() / $item->get_total(), 2) * 100 : 0;

                    $itemTotal = preg_replace('/^-/', '', ($item->get_total() / $itemQuantity));
                    $itemTotalTax = preg_replace('/^-/', '', ($item->get_total_tax() / $itemQuantity));
                    $vatPct = 0;
                    $totalAmount = (float)$itemTotal + (float)$itemTotalTax;

                    if ($discardCouponVat) {
                        $vatPct = $amountPct;
                        $totalAmount = (float)$itemTotal;
                    }

                    if ($itemTotal > 0) {
                        $return = true;
                        $connection->addOrderLine(
                            $articleId,
                            $product->get_title(),
                            $totalAmount,
                            $vatPct,
                            '',
                            'DISCOUNT',
                            $rowsLeftToHandle
                        );
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param WC_Order|WC_Order_Refund $order
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    private function hasCustomAfterShopShipping($order): bool
    {
        $shippingTotal = 0;

        if ($order instanceof WC_Order || $order instanceof WC_Order_Refund) {
            $shippingTotal = $order->get_shipping_total();
            // Depending of the instance.
            /** @noinspection NotOptimalIfConditionsInspection */
            if ($order instanceof WC_Order_Refund) {
                $refundOrderInfo = new WC_Order($order->get_id());
                $shippingRefunded = (float)$refundOrderInfo->get_total_shipping_refunded();
            } else {
                $shippingRefunded = (float)$order->get_total_shipping_refunded();
            }

            // Check if shipping has been refunded already.
            if ((float)$shippingTotal === $shippingRefunded) {
                return false;
            }

            $shippingTotal = (float)preg_replace('/^-/', '', $shippingTotal);
        }

        return $shippingTotal > 0;
    }

    /**
     * Natural flow for refunds.
     *
     * @param $orderId
     * @param $refundId
     * @throws Exception
     * @since 0.0.1.0
     */
    public function refundResursOrder($orderId, $refundId): bool
    {
        $return = false;
        $order = Data::getResursOrderIfExists($orderId);

        if ($order['order'] instanceof WC_Order) {
            $refundObject = new WC_Order_Refund($refundId);
            $resursOrder = Data::getResursReference($order);

            $resursConnection = (new ResursBankAPI())->getConnection();
            $resursConnection->setPreferredPaymentFlowService(CheckoutType::SIMPLIFIED_FLOW);

            // TODO: Store this information as metadata instead so each order gets handled
            // TODO: properly in aftershop mode.
            $discardCouponVat = (bool)Data::getResursOption('discard_coupon_vat');

            if (!$resursConnection->canAnnul($resursOrder) && !$resursConnection->canCredit($resursOrder)) {
                return true;
            }
            $refundItems = $refundObject->get_items();
            $itemCount = 0;
            if (is_array($refundItems) && count($refundItems)) {
                /** @var WC_Order_Item_Product $item */
                foreach ($refundItems as $item) {
                    $itemCount++;
                    $this->addRefundRow($resursConnection, $item, $discardCouponVat, $refundObject);
                }
            }

            // Collected request.
            $return = $this->refundPaymentRequest(
                $resursConnection,
                $resursOrder,
                $refundObject,
                $order,
                $itemCount
            );
        }

        return $return;
    }

    /**
     * @param ResursBank $resursConnection
     * @param string $resursOrder
     * @param WC_Order_Refund $refundObject
     * @param $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private function refundPaymentRequest(
        $resursConnection,
        $resursOrder,
        $refundObject,
        $order,
        $itemCount
    ): bool {
        $return = false;
        $totalDiscount = (float)$order['order']->get_total_discount();
        if ($totalDiscount) {
            $resursConnection->setGetPaymentMatchKeys(['artNo', 'description', 'unitMeasure']);
        }

        $hasCustomShipping = $this->hasCustomAfterShopShipping($refundObject);
        if ($hasCustomShipping) {
            $itemCount++;
            $this->addShippingOrderLine(
                $this->getPositiveValueFromNegative($refundObject->get_shipping_tax()),
                $this->getPositiveValueFromNegative($refundObject->get_shipping_total()),
                $resursConnection
            );
        }

        try {
            WooCommerce::applyMock('refundException');
            // Considering include $refundPriceAlwaysOverride? Do that here.
            $return = $resursConnection->paymentCancel(
                $resursOrder,
                [],
                $totalDiscount > 0
            );
            $order['order']->add_order_note(
                sprintf(
                    __(
                        'Refund/cancellation request for %d item(s) sent to Resurs Bank successfully.',
                        'resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $itemCount
                )
            );
        } catch (Exception $e) {
            Data::setLogException($e, __FUNCTION__);
            $order['order']->add_order_note(
                sprintf(
                    __(
                        'Resurs Bank refunding error: %s (%s).',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    $e->getMessage(),
                    $e->getCode()
                )
            );
        }

        return $return;
    }

    /**
     * @param ResursBank $connection
     * @param WC_Order_Item_Product $item
     * @param bool $discardCouponVat
     * @param WC_Order_Refund $refundObject
     * @throws Exception
     * @since 0.0.1.0
     */
    private function addRefundRow($connection, $item, $discardCouponVat, $refundObject)
    {
        $amountPct = !is_nan(
            @round($item->get_total_tax() / $item->get_total(), 2) * 100
        ) ? @round($item->get_total_tax() / $item->get_total(), 2) * 100 : 0;

        /** @var WC_Product $product */
        $product = $item->get_product();

        $itemQuantity = $this->getPositiveValueFromNegative($item->get_quantity());
        $articleId = WooCommerce::getProperArticleNumber($product);
        $itemTotal = $this->getPositiveValueFromNegative($item->get_total() / $itemQuantity);
        $itemTotalTax = $this->getPositiveValueFromNegative($item->get_total_tax() / $itemQuantity);

        // Defaults.
        $realAmount = $itemTotal;
        $vatPct = $amountPct;
        $refundDiscount = $refundObject->get_discount_total();

        /**
         * $hasRefundDiscount is considered boolean even if it is at this point has a discount value.
         */
        if ((float)$refundDiscount && $discardCouponVat) {
            $realAmount = $itemTotal + $itemTotalTax;
            $vatPct = 0;
        }

        $connection->addOrderLine(
            $articleId,
            $product->get_title(),
            $realAmount,
            $vatPct,
            '',
            'ORDER_LINE',
            $itemQuantity
        );
    }

    /**
     * @param $newSlug
     * @param $order
     * @throws Exception
     * @since 0.0.1.0
     */
    private function handleOrderByNewSlug($newSlug, $order)
    {
        $afterShopResponseString = '';

        $wpHelper = new wpHelper();
        $resursConnection = (new ResursBankAPI())->getConnection();

        // Userdata that should follow with the afterShopFlow when changing order status on Resurs side,
        // for backtracking actions.
        $resursConnection->setLoggedInUser($wpHelper->getUserInfo('user_login'));

        $resursReference = Data::getResursReference($order);
        switch ($newSlug) {
            case 'completed':
                // Make sure we also handle instant finalizations.
                if ($resursConnection->canDebit($order['ecom'])) {
                    $fullAfterShopRequest = $this->isFullAfterShopRequest($resursReference, $resursConnection);
                    if (!$fullAfterShopRequest) {
                        $this->hasCustomAfterShopOrderLines($order['order'], $resursConnection);
                    }
                    try {
                        // Add feature here, for which we look for and add discounts and shipping if necessary.
                        $finalizeResponse = $resursConnection->finalizePayment(
                            $resursReference,
                            null,
                            false,
                            $fullAfterShopRequest
                        );
                        $afterShopResponseString = $finalizeResponse ?
                            __(
                                'Success.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            ) : __(
                                'Failed without receiving any exception.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            );
                    } catch (Exception $e) {
                        $afterShopResponseString = $e->getMessage();
                    }
                }
                break;
            case 'cancelled':
            case 'refunded':
                if ($resursConnection->canCredit($order['ecom']) ||
                    $resursConnection->canAnnul($order['ecom'])
                ) {
                    $fullAfterShopRequest = $this->isFullAfterShopRequest($resursReference, $resursConnection);
                    if (!$fullAfterShopRequest) {
                        $this->hasCustomAfterShopOrderLines($order['order'], $resursConnection);
                    }

                    // When an order is fully refunded or cancelled (as this slug represents), we should follow the
                    // full cancellation method. As it seems, in v2.x cancellations and refunds are separated into
                    // two different sections with identical code except for the slug name.
                    try {
                        $cancelResponse = $resursConnection->cancelPayment(
                            $resursReference,
                            null,
                            $fullAfterShopRequest
                        );
                        $afterShopResponseString = $cancelResponse ?
                            __(
                                'Success.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            ) : __(
                                'Failed without receiving any exception.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            );
                    } catch (Exception $e) {
                        $afterShopResponseString = $e->getMessage();
                    }
                }
                break;
            default:
        }

        if (!empty($afterShopResponseString)) {
            WooCommerce::setOrderNote(
                $order['order'],
                __(
                    sprintf(
                        'WooCommerce signalled "%s"-request. Sent %s to Resurs Bank with result: %s.',
                        $newSlug,
                        $newSlug,
                        $afterShopResponseString
                    ),
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                )
            );
        }
    }

    /**
     * Queued status handler. Should not be called directly as it is based on WC_Queue.
     *
     * @param $order
     * @param $status
     * @param $notice
     * @throws Exception
     * @since 0.0.1.0
     * @link https://github.com/woocommerce/woocommerce/wiki/WC_Queue---WooCommerce-Worker-Queue
     */
    public function updateOrderStatusByQueue($order = '', $status = '', $notice = '')
    {
        if (!empty($status)) {
            $properOrder = WooCommerce::getProperOrder($order, 'order');

            $currentStatus = $properOrder->get_status();
            if ($currentStatus !== $status) {
                $properOrder->update_status(
                    $status,
                    WooCommerce::getOrderNotePrefixed($notice)
                );
                Data::canLog(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf(
                        __(
                            'Queued Status Handler: Updated status for %s to %s with notice: %s',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $order,
                        $status,
                        $notice
                    )
                );
            } else {
                Data::canLog(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf(
                        __(
                            'Queued Status Handler: Status for %s not updated to %s, because that ' .
                            'status was already set.',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        $order,
                        $status
                    )
                );
            }
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public function mockEmptyPriceInfoHtml(): string
    {
        return '';
    }

    /**
     * @param $return
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection BadExceptionsProcessingInspection
     */
    public function getAvailableAutoDebitMethods($return)
    {
        WooCommerce::setSessionValue('rb_requesting_debit_methods', true);
        // If we are saving or are somewhere else than in the payment methods section, we don't need
        // to run this controller as it is only used for visuals.
        if (!isset($_REQUEST['save']) &&
            Data::getRequest('section') === 'payment_methods'
        ) {
            try {
                $paymentMethodList = ResursBankAPI::getPaymentMethods(true);
            } catch (Exception $e) {
                Data::setLogException($e, __FUNCTION__);
                $return = [
                    'default' => __(
                        'Payment Methods are currently unavailable!',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ];
            }
            if (isset($paymentMethodList) && is_array($paymentMethodList)) {
                $return['default'] = __(
                    'Default (Choice made by plugin)',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                );
                foreach ($paymentMethodList as $method) {
                    if ($method->type === 'PAYMENT_PROVIDER') {
                        $return[$method->specificType] = $method->specificType;
                    }
                }
            }
        }
        WooCommerce::setSessionValue('rb_requesting_debit_methods', false);

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockCreateIframeException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockCallbackUpdateException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockGetPaymentMethodsException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockAnnuityFactorConfigException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockRefundException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @param $function
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getMockException($function)
    {
        $exceptionCode = 470;
        Data::canLog(
            Data::LOG_INFO,
            sprintf(
                __('Mocked Exception in action. Throwing MockException for function %s, with error code %d.'),
                $function,
                $exceptionCode
            )
        );

        throw new RuntimeException(
            sprintf(
                'MockException: %s',
                $function
            ),
            $exceptionCode
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockUpdateCallbackException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockUpdatePaymentFailure()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @param $defaultPrefix
     * @return mixed
     * @since 0.0.1.0
     */
    public function getDefaultOrderNotePrefix($defaultPrefix)
    {
        if (!empty(Data::getResursOption('order_note_prefix'))) {
            $defaultPrefix = Data::getResursOption('order_note_prefix');
        }
        return $defaultPrefix;
    }

    /**
     * Custom content for part payment data.
     * @return string
     * @since 0.0.1.0
     */
    public function getPartPaymentWidgetPage($return): string
    {
        $partPaymentWidgetId = Data::getResursOption('part_payment_template');
        if ($partPaymentWidgetId) {
            $return = get_post($partPaymentWidgetId)->post_content;
        }

        return $return;
    }

    /**
     * @param $url
     * @param $methodInformation
     * @since 0.0.1.0
     * @noinspection NotOptimalRegularExpressionsInspection
     */
    public function getMethodIconByContent($url, $methodInformation)
    {
        $iconSetting = Data::getResursOption('payment_method_icons');
        foreach ($methodInformation as $item) {
            $itemName = strtolower($item);
            if (preg_match('/^pspcard_/i', strtolower($item))) {
                // Shorten up credit cards.
                $itemName = 'pspcard';
            }
            $byItem = sprintf('method_%s.png', $itemName);

            $imageByMethodContent = Data::getImage($byItem);
            if ($imageByMethodContent) {
                $url = $imageByMethodContent;
                break;
            }
        }

        if (empty($url) &&
            $iconSetting === 'specifics_and_resurs' &&
            $methodInformation['type'] !== 'PAYMENT_PROVIDER'
        ) {
            $url = Data::getImage('resurs-logo.png');
        }

        return $url;
    }

    /**
     * @param $filterIsActive
     */
    public function setRcoDisabledWarning($filterIsActive)
    {
        if ($filterIsActive) {
            Data::setLogInternal(
                Data::LOG_WARNING,
                sprintf(
                    __(
                        'The filter "%s" is currently put in an active state by an unknown third party plugin. This ' .
                        'setting is deprecated and no longer fully supported. It is highly recommended to disable ' .
                        'or remove the filter entirely and solve the problem that required this from start somehow ' .
                        'else.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                    'resursbank_temporary_disable_checkout'
                )
            );
        }
    }

    /**
     * @param $scriptList
     * @return mixed
     * @since 0.0.1.0
     */
    public function getRcoLoaderScripts($scriptList)
    {
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $scriptList['resursbank_rco_legacy'] = 'vendor/resurscheckoutjs/resurscheckout.js';
        }

        return $scriptList;
    }
}
