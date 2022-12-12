<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection LongInheritanceChainInspection */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Cart;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Woocommerce\Modules\Payment\Converter\Shipping as ShippingItem;
use WC_Cart;


/**
 * Convert shipping data to OrderLine.
 */
class Shipping extends ShippingItem
{
    /**
     * Create MAPI orderLine from WooCommerce shipping.
     *
     * @return OrderLine[]
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public static function getOrderLines(WC_Cart $cart): array
    {
        return [ShippingItem::toOrderLine(
            total: self::getTotal(cart: $cart),
            tax: self::getTax(cart: $cart)
        )];
    }

    /**
     * Wrapper to safely retrieve shipping amount.
     *
     * @param WC_Cart $cart
     * @return float
     * @throws IllegalValueException
     */
    public static function getTotal(WC_Cart $cart): float
    {
        $result = $cart->get_shipping_total();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_shipping_total()" is not numeric.'
            );
        }

        return (float) $result;
    }

    /**
     * Wrapper to safely resolve shipping tax.
     *
     * @param WC_Cart $cart
     * @return float
     * @throws IllegalValueException
     */
    public static function getTax(WC_Cart $cart): float
    {
        $result = $cart->get_shipping_tax();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_shipping_tax()" is not numeric.'
            );
        }

        return (float) $result;
    }
}
