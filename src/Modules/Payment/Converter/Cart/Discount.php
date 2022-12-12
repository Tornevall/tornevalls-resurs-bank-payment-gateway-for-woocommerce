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
use Resursbank\Woocommerce\Modules\Payment\Converter\Discount as DiscountItem;
use WC_Cart;
use WC_Coupon;

use function array_map;
use function is_array;
use function is_float;

/**
 * Convert discount data to OrderLine.
 */
class Discount extends DiscountItem
{
    /**
     * Get MAPI orderLine from WooCommerce coupons.
     *
     * @param WC_Cart $cart
     * @return array
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
        return !self::isCouponsEnabled() ?
            [] : array_map(
                callback: static fn (WC_Coupon $item) =>
                DiscountItem::toOrderLine(
                    coupon: $item,
                    inclTax: self::getAmount(cart: $cart, coupon: $item),
                    exclTax: self::getAmount(cart: $cart, coupon: $item, exclTax: true),
                ),
                array: self::getCoupons(cart: $cart)
            );
    }

    /**
     * Wrapper to safely retrieve applied coupons.
     *
     * @param WC_Cart $cart
     * @return array
     * @throws IllegalValueException
     */
    public static function getCoupons(WC_Cart $cart): array
    {
        $result = $cart->get_coupons();

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_coupons()" is not an array.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Cart $cart
     * @param WC_Coupon $coupon
     * @param bool $exclTax
     * @return float
     * @throws IllegalValueException
     */
    public static function getAmount(
        WC_Cart $cart,
        WC_Coupon $coupon,
        bool $exclTax = false
    ): float {
        $result = $cart->get_coupon_discount_amount(
            code: DiscountItem::getCode(coupon: $coupon),
            ex_tax: $exclTax
        );

        if (!is_float(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$cart->get_coupon_discount_amount()" is not a float.'
            );
        }

        return $result;
    }
}
