<?php

namespace ResursBank\Service;

use Exception;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Helpers\WooCommerce;
use ResursBank\Helpers\WordPress;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use WC_Coupon;
use WC_Product;

/**
 * @since 0.0.1.0
 */
class OrderHandler extends ResursDefault
{
    /**
     * @var array
     * @since 0.0.1.0
     */
    private $cart;

    /**
     * @var Api
     * @since 0.0.1.0
     */
    protected $API;

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
    public function setPreparedOrderLines()
    {
        $this
            ->setOrderRows()
            ->setCoupon()
            ->setShipping()
            ->setFee();

        return $this;
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
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getOrderLines()
    {
        return $this->API->getConnection()->getOrderLines();
    }

    /**
     * Returns a Resurs API Link to a parent caller (ResursDefault). After order rows are created, it is important
     * for the already created link to only update changes that occurred during the order line rendering here as
     * the link may already contain customer data.
     *
     * @return Api
     * @since 0.0.1.0
     */
    public function getApi()
    {
        return $this->API;
    }

    /**
     * Sets up a Resurs API Link that is already in use instead of recreating the API link. This is an important
     * step for ResursDefault to be able to pass orderline handling to this section.
     *
     * @param $api
     * @return $this
     * @since 0.0.1.0
     */
    public function setApi($api)
    {
        $this->API = $api;

        return $this;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setOrderRows()
    {
        if (WooCommerce::getValidCart()) {
            foreach (WooCommerce::getValidCart(true) as $item) {
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
        }

        return $this;
    }
}
