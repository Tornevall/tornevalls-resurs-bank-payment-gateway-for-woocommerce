<?php

/** @noinspection LongInheritanceChainInspection */

namespace ResursBank\Service;

use Exception;
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Modules\Gateway\ResursDefault;
use WC_Order;
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
}
