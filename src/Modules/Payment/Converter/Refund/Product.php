<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter\Refund;

use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Woocommerce\Util\Translator;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

use function array_shift;
use function is_array;
use function is_string;

/**
 * Basic methods to convert WC_Order_Item_Product to OrderLine.
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
            quantity: self::getQuantity(
                product: $product
            ) === 0.0 ? 1 : -self::getQuantity(
                product: $product
            ),
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: self::getVatRate(product: $product),
            totalAmountIncludingVat: -round(
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
     * Get total price of product excluding tax.
     *
     * @throws IllegalValueException
     */
    public static function getTotal(
        WC_Order_Item_Product $product
    ): float {
        $result = $product->get_total();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Total amount is not a number.'
            );
        }

        // Our API expects two decimals, WC will sometimes give us more.
        return round(num: (float) $result, precision: 2);
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
        $result = $product->get_subtotal();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Total amount is not numeric.'
            );
        }

        // Our API expects two decimals, WC will sometimes give us more.
        return round(num: (float) $result, precision: 2);
    }

    /**
     * Resolve order item vat (tax) rate.
     *
     * NOTE: This is also utilised by methods which compile discount data.
     *
     * @todo WC_Tax::get_rates returning an array suggests there can be several taxes per item, investigate.
     */
    public static function getVatRate(WC_Order_Item_Product $product): float
    {
        /* Passing get_tax_class() result without validation since anything it
           can possibly return should be acceptable to get_rates() */
        $rates = WC_Tax::get_rates(tax_class: $product->get_tax_class());

        if (is_array(value: $rates)) {
            $rates = array_shift(array: $rates);
        }

        /* Note that the value is rounded since we can sometimes receive values
           with more than two decimals, but our API expects max two. */
        return (
            is_array(value: $rates) &&
            isset($rates['rate']) &&
            is_numeric(value: $rates['rate'])
        ) ? round(num: (float) $rates['rate'], precision: 2) : 0.0;
    }

    /**
     * Get product vat (tax) amount.
     *
     * @throws IllegalValueException
     */
    public static function getTotalVat(
        WC_Order_Item_Product $product
    ): float {
        $result = $product->get_total_tax();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Total tax amount is not a number.'
            );
        }

        // Our API expects two decimals, WC will sometimes give us more.
        return round(num: (float) $result, precision: 2);
    }

    /**
     * Get product subtotal VAT amount.
     *
     * @throws IllegalValueException
     */
    public static function getSubtotalVat(
        WC_Order_Item_Product $product
    ): float {
        $result = $product->get_subtotal_tax();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Total tax amount is not a number.'
            );
        }

        // Our API expects two decimals, WC will sometimes give us more.
        return round(num: (float) $result, precision: 2);
    }

    /**
     * Type-safe wrapper to resolve quantity from order item.
     *
     * @throws IllegalValueException
     */
    private static function getQuantity(WC_Order_Item_Product $product): float
    {
        $result = $product->get_quantity();

        if (!is_numeric(value: $result)) {
            throw new IllegalValueException(
                message: 'Order item quantity is not a number.'
            );
        }

        // Our API expects a float.
        return (float) $result;
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
     * @throws IllegalValueException
     */
    private static function getSku(WC_Order_Item_Product $product): string
    {
        $result = self::getOriginalProduct(product: $product)->get_sku();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Failed to resolve SKU from order item.'
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
