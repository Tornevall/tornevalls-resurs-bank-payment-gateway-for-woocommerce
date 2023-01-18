<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Order;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Utilities\Tax;
use WC_Order;

/**
 * Collect shipping data as OrderLine objects from WC_Order instance.
 */
class Shipping
{
    /**
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public static function getOrderLine(WC_Order $order): OrderLine
    {
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
     * Type-safe wrapper to extract shipping total incl. tax.
     *
     * @throws IllegalValueException
     */
    private static function getTotal(WC_Order $order): float
    {
        $total = $order->get_shipping_total();

        if (!is_numeric(value: $total)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_shipping_total()" is not numeric.'
            );
        }

        return (float) $total;
    }

    /**
     * Type-safe wrapper to extract vat rate applied to shipping amount.
     *
     * @throws IllegalValueException
     */
    private static function getTax(WC_Order $order): float
    {
        $result = $order->get_shipping_tax();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_shipping_tax()" is not numeric.'
            );
        }

        return (float) $result;
    }
}
