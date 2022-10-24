<?php

/** @noinspection LongInheritanceChainInspection */

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\FormatException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order\OrderLineCollection;
use Resursbank\Ecom\Module\Payment\Models\CreatePaymentRequest\Order\OrderLine;
use Resursbank\Ecom\Module\Rco\Models\OrderLine as RcoOrderLine;
use ResursBank\Exception\MapiCredentialsException;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\ResursBankAPI;
use WC_Cart;
use WC_Coupon;
use WC_Order;
use WC_Product;
use function count;
use function is_array;
use function is_object;

/**
 * Class for which active order creations are handled.
 *
 * @since 0.0.1.0
 */
class OrderHandler extends ResursDefault
{
    /**
     * getAddress form fields translated into wooCommerce address data.
     * @var array
     * @since 0.0.1.0
     */
    private static array $getAddressTranslation = [
        'first_name' => 'firstName',
        'last_name' => 'lastName',
        'address_1' => 'addressRow1',
        'address_2' => 'addressRow2',
        'city' => 'postalArea',
        'postcode' => 'postalCode',
        'country' => 'country',
    ];

    /**
     * @param $cart
     * @since 0.0.1.0
     */
    public function setCart($cart)
    {
        $this->cart = $cart;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setPreparedOrderLines(): self
    {
        // @todo WOO-764

        // Reactivate this when it feels all right again (RCO?).
        /*
        $this
            ->setOrderRows()
            ->setCoupon()
            ->setShipping()
            ->setFee();
        */

        return $this;
    }

    /**
     * @return OrderLineCollection
     * @throws IllegalTypeException
     * @throws Exception
     */
    public function getPreparedMapiOrderLines(): OrderLineCollection
    {
        return new OrderLineCollection(
            data: array_merge(
                $this->getMapiArticleRows(),
                $this->getMapiShippingRows(),
                $this->getMapiCouponRows(),
                $this->getMapiFeeRows()
            )
        );
    }

    /**
     * @throws Exception
     */
    public function getMapiArticleRows(): array
    {
        $return = [];

        if (WooCommerce::getValidCart(true)) {
            /** @var WC_Cart $cartList */
            $cartList = WooCommerce::getValidCart(true);
            foreach ($cartList as $item) {
                /**
                 * Data object is of type WC_Product_Simple actually.
                 * @var WC_Product $productData
                 */
                $productData = $item['data'];

                if ($productData !== null) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf(
                            'Add order line %s.',
                            $productData->get_id()
                        )
                    );
                    $return[] = $this->getMapiOrderProductRow(
                        orderLineType: OrderLineType::PHYSICAL_GOODS,
                        productData: $productData,
                        wcProductItem: $item
                    );
                }
            }
        }

        return $return;
    }

    /**
     * @throws IllegalValueException
     */
    public function getMapiFeeRows(): array
    {
        $return = [];

        // In an initial state of this build, we decided to not in include fee's in the order lines for some
        // reasons. One reason was that the fee's in WooCommerce potentially could conflict with future fee's
        // set at Resurs Bank. The setup with fee's has also been considered not recommended, since they
        // sometimes are not allowed to be in use anyway.
        //
        // The fee setup was however already in place when this was decided, so instead of removing it, we made
        // this optional in cases where we want it back.
        if (isset(WC()->cart) && WC()->cart instanceof WC_Cart && Data::isPaymentFeeAllowed()) {
            $fees = WC()->cart->get_fees();
            if (is_array($fees) && count($fees)) {
                foreach ($fees as $fee) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf('Apply payment fee %s', $fee->amount)
                    );

                    $return[] = $this->getMapiCustomOrderLine(
                        orderLineType: OrderLineType::FEE,
                        description: $fee->name,
                        reference: $fee->id,
                        unitAmountIncludingVat: $fee->amount,
                        vatRate: round(
                            $fee->tax / $fee->total,
                            wc_get_price_decimals()
                        ) * 100
                    );
                }
            }
        }

        return $return;
    }

    /**
     * @return array
     * @throws IllegalValueException
     */
    public function getMapiShippingRows(): array
    {
        $return= [];
        if ($this->cart->get_shipping_total() > 0) {
            Data::setDeveloperLog(
                __FUNCTION__,
                sprintf('Apply shipping fee %s', $this->cart->get_shipping_total())
            );

            $return[] = $this->getMapiCustomOrderLine(
                orderLineType: OrderLineType::SHIPPING,
                description: WordPress::applyFilters(
                    filterName: 'getShippingDescription',
                    value: __('Shipping', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce')
                ),
                reference: WordPress::applyFilters('getShippingName', 'shipping'),
                unitAmountIncludingVat: $this->cart->get_shipping_total(),
                vatRate: round(
                    $this->cart->get_shipping_tax() / $this->cart->get_shipping_total(),
                    wc_get_price_decimals()
                ) * 100
            );
        }

        return $return;
    }

    /**
     * @return array
     * @throws IllegalValueException
     */
    public function getMapiCouponRows(): array
    {
        $return = [];

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

                // Note, there are several ways to handle coupon vat (just like Magento). This is basically
                // driven by the option discard_coupon_vat, that decides whether to include the coupon vat or not.

                // TODO: Store this information as metadata instead so each order gets handled
                // TODO: properly in payment management mode.
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

                $return[] = $this->getMapiCustomOrderLine(
                    orderLineType: OrderLineType::DISCOUNT,
                    description:  WordPress::applyFilters(
                        'getCouponDescription',
                        $couponDescription
                    ),
                    reference: $coupon->get_id(),
                    unitAmountIncludingVat: 0 - $this->cart->get_coupon_discount_amount(
                        $coupon->get_code(),
                        WordPress::applyFilters('couponsExTax', !$discardCouponVat, $coupon)
                    ),
                    vatRate: WordPress::applyFilters('getCouponVatPct', !$discardCouponVat ? $vatPct : 0)
                );
            }
        }

        return $return;
    }

    /**
     * Apply Resurs internal payment fee naturally.
     *
     * @return $this
     * @throws Exception
     * @since 0.0.1.5
     */
    private function setFee(): self
    {
        if (isset(WC()->cart) && WC()->cart instanceof WC_Cart && Data::isPaymentFeeAllowed()) {
            $fees = WC()->cart->get_fees();
            if (is_array($fees) && count($fees)) {
                foreach ($fees as $fee) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf('Apply payment fee %s', $fee->amount)
                    );

                    $this->API->getConnection()->addOrderLine(
                        $fee->id,
                        $fee->name,
                        $fee->amount,
                        round(
                            $fee->tax / $fee->total,
                            wc_get_price_decimals()
                        ) * 100
                    );
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setShipping(): self
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
                WordPress::applyFilters(
                    'getShippingDescription',
                    __('Shipping', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce')
                ),
                $this->cart->get_shipping_total(),
                round(
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
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setCoupon(): self
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

                // TODO: Store this information as metadata instead so each order gets handled
                // TODO: properly in aftershop mode.
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
    private function setOrderRows(): OrderHandler
    {
        if (WooCommerce::getValidCart()) {
            /** @var WC_Cart $cartList */
            $cartList = WooCommerce::getValidCart(true);
            foreach ($cartList as $item) {
                /**
                 * Data object is of type WC_Product_Simple actually.
                 * @var WC_Product $productData
                 */
                $productData = $item['data'];

                if ($productData !== null) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf(
                            'Add order line %s.',
                            $productData->get_id()
                        )
                    );
                    $this->getMapiOrderProductRow(
                        orderLineType: OrderLineType::PHYSICAL_GOODS,
                        productData: $productData,
                        wcProductItem: $item
                    );
                }
            }
        }

        return $this;
    }

    /**
     * @param WC_Order $order
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getCustomerRealAddress($order): bool
    {
        $return = false;
        $resursPayment = Data::getOrderMeta('resurspayment', $order);
        if (is_object($resursPayment) && isset($resursPayment->customer)) {
            $billingAddress = $order->get_address('billing');
            $orderId = $order->get_id();
            if ($orderId > 0 && isset($resursPayment->customer->address)) {
                foreach (self::$getAddressTranslation as $item => $value) {
                    if (isset($billingAddress[$item], $resursPayment->customer->address->{$value}) &&
                        $billingAddress[$item] !== $resursPayment->customer->address->{$value}
                    ) {
                        update_post_meta(
                            $orderId,
                            sprintf('_billing_%s', $item),
                            $resursPayment->customer->address->{$value}
                        );
                        $return = true;
                    }
                }
            }
        }

        if ($return) {
            $syncNotice = __(
                'Resurs Bank billing address mismatch with current address in order. ' .
                'Data has synchronized with Resurs Bank billing data.',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            );
            $order->add_order_note($syncNotice);
            Data::setOrderMeta($order, 'customerSynchronization', date('Y-m-d H:i:s', time()));
            Data::writeLogNotice($syncNotice);
        }

        return $return;
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getOrderLines(): array
    {
        return $this->API->getConnection()->getOrderLines();
    }

    /**
     * Returns a Resurs API Link to a parent caller (ResursDefault). After order rows are created, it is important
     * for the already created link to only update changes that occurred during the order line rendering here as
     * the link may already contain customer data.
     *
     * @return ResursBankAPI
     * @since 0.0.1.0
     */
    public function getApi(): ResursBankAPI
    {
        return $this->API;
    }

    /**
     * Sets up a Resurs API Link that is already in use instead of recreating the API link. This is an important
     * step for ResursDefault to be able to pass order line handling to this section.
     *
     * @param $api
     * @return $this
     * @since 0.0.1.0
     */
    public function setApi($api): self
    {
        $this->API = $api;

        return $this;
    }
}
