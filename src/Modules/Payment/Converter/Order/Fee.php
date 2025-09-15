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
use WC_Order_Item_Fee;

/**
 * Convert WC_Order_Item_Fee to OrderLine.
 */
class Fee
{
    /**
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws AttributeCombinationException
     */
    public static function toOrderLine(
        WC_Order_Item_Fee $fee
    ): OrderLine {
        return new OrderLine(
            quantity: 1.00,
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Order::getVatRate(item: $fee),
            totalAmountIncludingVat: round(
                num: self::getSubtotal(fee: $fee) +
                    self::getSubtotalVat(fee: $fee),
                precision: 2
            ),
            description: Translator::translate(phraseId: 'fee'),
            reference: 'fee',
            type: OrderLineType::FEE
        );
    }

    /**
     * Get total of all fee prices, excluding tax.
     *
     * NOTE: This is also utilised by methods to compile discount.
     *
     * @throws IllegalValueException
     */
    private static function getSubtotal(
        WC_Order_Item_Fee $fee
    ): float {
        return Order::convertFloat(value: $fee->get_amount());
    }

    /**
     * Get fee subtotal VAT amount.
     *
     * @throws IllegalValueException
     */
    private static function getSubtotalVat(
        WC_Order_Item_Fee $fee
    ): float {
        return Order::convertFloat(value: $fee->get_total_tax());
    }
}
