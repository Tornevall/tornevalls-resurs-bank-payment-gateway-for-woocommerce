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
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order_Item_Shipping;

/**
 * Convert WC_Order_Item_Shipping to OrderLine.
 */
class Shipping
{
    /**
     * @throws IllegalValueException
     */
    public static function toOrderLine(WC_Order_Item_Shipping $item): OrderLine
    {
        return new OrderLine(
            quantity: 1,
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Order::getVatRate(item: $item),
            totalAmountIncludingVat: round(
                num: self::getSubtotal(item: $item) +
                    self::getSubtotalVat(item: $item),
                precision: 2
            ),
            description: (string) $item->get_method_title(),
            reference: self::getReference(),
            type: OrderLineType::SHIPPING
        );
    }

    /**
     * Since there is nothing unique guaranteed to us to separate shipping
     * methods we will suffix the reference with a timestamp. For example, you
     * could apply two Flat Rate options, with the same amount, and the item id
     * is not available to us during checkout. Thus, none of these values can be
     * utilised to create a unique payment line.
     *
     * When we modify a payment we will cancel all existing lines, so it won't
     * matter that we reference the payment line this way as we never need to
     * identify the relationship between the WC order and the payment at
     * Resurs Bank.
     */
    private static function getReference(): string
    {
        return 'shipping-' . time();
    }

    /**
     * Get total of shipping item, excluding tax.
     *
     * @throws IllegalValueException
     */
    private static function getSubtotal(
        WC_Order_Item_Shipping $item
    ): float {
        return Order::convertFloat(value: $item->get_total());
    }

    /**
     * Get item subtotal VAT amount.
     *
     * @throws IllegalValueException
     */
    private static function getSubtotalVat(
        WC_Order_Item_Shipping $item
    ): float {
        return Order::convertFloat(value: $item->get_total_tax());
    }
}
