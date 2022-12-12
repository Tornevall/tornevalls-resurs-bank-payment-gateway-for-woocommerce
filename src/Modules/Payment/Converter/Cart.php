<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection LongInheritanceChainInspection */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use WC_Cart;
use WC_Product;
use WC_Product_Simple;
use Resursbank\Woocommerce\Modules\Payment\Converter\Cart\Discount;
use Resursbank\Woocommerce\Modules\Payment\Converter\Cart\Shipping;

use WooCommerce;
use function array_merge;
use function is_array;

/**
 * Conversion of WC_Cart to OrderLineCollection.
 */
class Cart
{
    /**
     * @return OrderLineCollection
     * @throws IllegalTypeException
     * @throws Exception
     */
    public static function getOrderLines(): OrderLineCollection
    {
        $cart = self::getCart();

        return new OrderLineCollection(
            data: $cart !== null ? array_merge(
                self::getProductOrderLines(cart: $cart),
                Shipping::getOrderLines(cart: $cart),
                Discount::getOrderLines(cart: $cart),
            ) : []
        );
    }

    /**
     * Create collection of orderLines from a valid WooCommerce cart
     * (default handler of products).
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
    public static function getProductOrderLines(WC_Cart $cart): array
    {
        $result = [];
        $cartContents = self::getCartContents(cart: $cart);

        /** @var array $item */
        foreach ($cartContents as $item) {
            /** @var WC_Product_Simple $productData */
            $productData = $item['data'] ?? null;
            $qty = $item['quantity'] ?? 1.0;

            if ($productData instanceof WC_Product) {
                $result[] = Product::toOrderLine(
                    product: $productData,
                    qty: (float) $qty,
                    orderLineType: OrderLineType::NORMAL
                );
            }
        }

        return $result;
    }

    /**
     * Wrapper to safely resolve cart contents.
     *
     * @param WC_Cart $cart
     * @return array
     * @throws IllegalValueException
     */
    public static function getCartContents(WC_Cart $cart): array
    {
        $result = $cart->get_cart();

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "get_cart()" is not an array.'
            );
        }

        return $result;
    }

    /**
     * @return WC_Cart|null
     */
    public static function getCart(): ?WC_Cart
    {
        $wc = WC();
        $cart = $wc instanceof WooCommerce ? WC()->cart : null;

        return $cart instanceof WC_Cart ? $cart : null;
    }
}
