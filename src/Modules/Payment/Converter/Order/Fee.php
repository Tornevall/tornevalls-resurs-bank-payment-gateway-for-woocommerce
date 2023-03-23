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
use WC_Order_Item_Fee;

/**
 * Convert WC_Order_Item_Fee to OrderLine.
 */
class Fee
{
    /**
     * @throws IllegalValueException
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
                precision: Order::getConfiguredDecimalPoints()
            ),
            description: Translator::translate(phraseId: 'fee'),
            reference: self::getReference(),
            type: OrderLineType::FEE
        );
    }

    /**
     * Since there is nothing unique guaranteed to us to separate fees, we will
     * suffix the reference with a timestamp. For example, you could apply two
     * fees with the same amount, thus there would nothing to help us separate
     * them into unique lines of the payment.
     *
     * When we modify a payment we will cancel all existing lines, so it won't
     * matter that we reference the payment line this way as we never need to
     * identify the relationship between the WC order and the payment at
     * Resurs Bank.
     */
    private static function getReference(): string
    {
        return 'fee-' . time();
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
