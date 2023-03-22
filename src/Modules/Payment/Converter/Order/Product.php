<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Order;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order_Item_Product;
use WC_Product;

use function is_string;

/**
 * Convert WC_Order_Item_Product to OrderLine.
 */
class Product
{
    /**
     * @throws IllegalValueException
     */
    public static function toOrderLine(
        WC_Order_Item_Product $product
    ): OrderLine {
        return new OrderLine(
            quantity: self::getQuantity(product: $product),
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Order::getVatRate(
                taxClass: (string) $product->get_tax_class()
            ),
            totalAmountIncludingVat: round(
                num: self::getSubtotal(
                    product: $product
                ) + self::getSubtotalVat(
                    product: $product
                ),
                precision: 2
            ),
            description: self::getTitle(product: $product),
            reference: self::getSku(product: $product),
            type: OrderLineType::NORMAL
        );
    }

    /**
     * Get quantity, defaults to 1 because manual refunds may specify 0 which
     * our API will reject.
     *
     * @throws IllegalValueException
     */
    public static function getQuantity(
        WC_Order_Item_Product $product
    ): float {
        $result = Order::convertFloat(value: $product->get_quantity());

        return $result === 0.00 ? 1.00 : $result;
    }

    /**
     * Get total of all product prices, excluding tax.
     *
     * NOTE: This is also utilised by methods to compile discount.
     *
     * @throws IllegalValueException
     */
    public static function getSubtotal(
        WC_Order_Item_Product $product
    ): float {
        return Order::convertFloat(value: $product->get_subtotal());
    }

    /**
     * Get product subtotal VAT amount.
     *
     * @throws IllegalValueException
     */
    public static function getSubtotalVat(
        WC_Order_Item_Product $product
    ): float {
        return Order::convertFloat(value: $product->get_subtotal_tax());
    }

    /**
     * @throws IllegalValueException
     */
    private static function getTitle(WC_Order_Item_Product $product): string
    {
        $result = self::getOriginalProduct(product: $product)->get_title();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Failed to resolve product title from order item.'
            );
        }

        return $result;
    }

    /**
     * Attempts to fetch SKU from product.
     *
     * @throws IllegalValueException
     */
    private static function getSku(WC_Order_Item_Product $product): string
    {
        $result = self::getOriginalProduct(product: $product)->get_sku();

        if (!is_string(value: $result) || $result === '') {
            Log::error(
                error: new EmptyValueException(
                    message: 'Failed to resolve SKU from product with id ' . $product->get_id() .
                             ' when parsing order line.'
                )
            );

            throw new IllegalValueException(
                message: Translator::translate(
                    phraseId: 'failed-to-resolve-sku-from-order-line-object'
                ) . '<br />' .
                         Translator::translate(
                             phraseId: 'could-not-complete-your-order-please-contact-support'
                         )
            );
        }

        return $result;
    }

    /**
     * @throws IllegalValueException
     */
    private static function getOriginalProduct(
        WC_Order_Item_Product $product
    ): WC_Product {
        $result = $product->get_product();

        if (!$result instanceof WC_Product) {
            throw new IllegalValueException(
                message: 'Order item product is not an instance of WC_Product.'
            );
        }

        return $result;
    }
}
