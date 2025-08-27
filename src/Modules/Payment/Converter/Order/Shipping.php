<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Order;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
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
     * @throws JsonException
     * @throws ReflectionException
     * @throws AttributeCombinationException
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
            reference: 'shipping',
            type: OrderLineType::SHIPPING
        );
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
