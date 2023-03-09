<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Order;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Utilities\Tax;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order;
use WC_Order_Refund;

/**
 * Collect shipping data as OrderLine objects from WC_Order or WC_Order_Refund
 */
class Shipping
{
    /**
     * @throws IllegalValueException
     */
    public static function getOrderLine(
        WC_Order|WC_Order_Refund $order
    ): OrderLine {
        $total = self::getTotal(order: $order);
        $tax = self::getTax(order: $order);

        return new OrderLine(
            quantity: 1,
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Tax::getRate(taxAmount: $tax, totalInclTax: $total),
            totalAmountIncludingVat: round(num: $total + $tax, precision: 2),
            description: Translator::translate(
                phraseId: 'shipping-description'
            ),
            reference: Translator::translate(
                phraseId: 'shipping-reference'
            ),
            type: OrderLineType::SHIPPING
        );
    }

    /**
     * Check if WC_Order|WC_Order_Refund has any shipping cost applied.
     *
     * @throws IllegalValueException
     */
    public static function isAvailable(WC_Order|WC_Order_Refund $order): bool
    {
        return self::getTotal(order: $order) + self::getTax(
            order: $order
        ) > 0.0;
    }

    /**
     * Type-safe wrapper to extract shipping total incl. tax.
     *
     * @throws IllegalValueException
     */
    private static function getTotal(WC_Order|WC_Order_Refund $order): float
    {
        return Order::convertFloat(value: $order->get_shipping_total());
    }

    /**
     * Type-safe wrapper to extract vat rate applied to shipping amount.
     *
     * @throws IllegalValueException
     */
    private static function getTax(WC_Order|WC_Order_Refund $order): float
    {
        return Order::convertFloat(value: $order->get_shipping_tax());
    }
}
