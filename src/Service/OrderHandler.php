<?php

/** @noinspection LongInheritanceChainInspection */

namespace ResursBank\Service;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\ResursBankAPI;
use Resursbank\Woocommerce\Util\Metadata;
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
     * @return OrderLineCollection
     * @throws IllegalTypeException
     * @throws Exception
     */
    public function getOrderLines(): OrderLineCollection
    {
        return new OrderLineCollection(
            data: array_merge(
                $this->getArticleRows(),
                $this->getShipping(),
                $this->getCoupons(),
                $this->getFees()
            )
        );
    }

    /**
     * Create collection of orderLines from a valid WooCommerce cart (default handler of products).
     *
     * @return array
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public function getArticleRows(): array
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
                    $return[] = $this->getProductRow(
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
     * Apply fee's to the order (this is not necessarily Resurs fee's but also wooCommerce based fee's.
     *
     * @return array
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws TranslationException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     */
    public function getFees(): array
    {
        $return = [];

        // Since Resurs don't add any fee's plugin-side, we only handle other built in fee's here.
        if (isset(WC()->cart) && WC()->cart instanceof WC_Cart) {
            $fees = WC()->cart->get_fees();
            if (is_array($fees) && count($fees)) {
                foreach ($fees as $fee) {
                    Data::setDeveloperLog(
                        __FUNCTION__,
                        sprintf('Apply payment fee %s', $fee->amount)
                    );

                    $return[] = $this->getCustomOrderLine(
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
     * Create MAPI orderLine from WooCommerce shipping.
     *
     * @return array
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public function getShipping(): array
    {
        $shipping = $this->cart->get_shipping_total();

        return !is_float($shipping) || $shipping < 0.01 ? [] : [
            $this->getCustomOrderLine(
                orderLineType: OrderLineType::SHIPPING,
                description: WordPress::applyFilters(
                    filterName: 'getShippingDescription',
                    value: Translator::translate('shipping-description')
                ),
                reference: WordPress::applyFilters(
                    filterName: 'getShippingReference',
                    value: Translator::translate('shipping-reference')
                ),
                unitAmountIncludingVat: $this->cart->get_shipping_total(),
                vatRate: round(
                    $this->cart->get_shipping_tax() / $shipping,
                    wc_get_price_decimals()
                ) * 100
            )
        ];
    }

    /**
     * Get MAPI orderLine from WooCommerce coupons.
     *
     * @return array
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public function getCoupons(): array
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
                $discardCouponVat = (bool)Data::getResursOption(key: 'discard_coupon_vat');
                $exTax = 0 - $this->cart->get_coupon_discount_amount($code);
                $incTax = 0 - $this->cart->get_coupon_discount_amount($code, ex_tax: false);
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

                $return[] = $this->getCustomOrderLine(
                    orderLineType: OrderLineType::DISCOUNT,
                    description: WordPress::applyFilters(
                        filterName: 'getCouponDescription',
                        value: $couponDescription
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
     * Customer address synchronization.
     *
     * @param WC_Order $order
     * @return bool
     * @throws Exception
     * @todo Do we still need this as MAPI may not give us the same opportunity?
     * @todo Suggested solution is to just remove this method together with the self::$getAddressTranslation.
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
                'resurs-bank-payments-for-woocommerce'
            );
            $order->add_order_note($syncNotice);
            Data::setOrderMeta($order, 'customerSynchronization', date('Y-m-d H:i:s', time()));
            Data::writeLogNotice($syncNotice);
        }

        return $return;
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
