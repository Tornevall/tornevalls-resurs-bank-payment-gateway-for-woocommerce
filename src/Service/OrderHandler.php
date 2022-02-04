<?php

/** @noinspection LongInheritanceChainInspection */

namespace ResursBank\Service;

use Exception;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\ResursBankAPI;
use WC_Cart;
use WC_Coupon;
use WC_Product;
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
    private static $getAddressTranslation = [
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
        $this
            ->setOrderRows()
            ->setCoupon()
            ->setShipping()
            ->setFee();

        return $this;
    }

    /**
     * @param $order
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
            $synchNotice = __(
                'Resurs Bank billing address mismatch with current address in order. ' .
                'Data has synchronized with Resurs Bank billing data.',
                'resurs-bank-payment-gateway-for-woocommerce'
            );
            Data::setOrderMeta($order, 'customerSynchronization', strftime('%Y-%m-%d %H:%M:%S', time()));
            Data::setLogNotice($synchNotice);
            WooCommerce::setOrderNote(
                $order,
                $synchNotice
            );
        }

        return $return;
    }

    /**
     * @return $this
     * @since 0.0.1.0
     * @todo Complete this.
     */
    private function setFee(): self
    {
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
                WordPress::applyFilters('getShippingDescription', __('Shipping', 'rbwc')),
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
                    $this->setOrderRow('ORDER_LINE', $productData, $item);
                }
            }
        }

        return $this;
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
