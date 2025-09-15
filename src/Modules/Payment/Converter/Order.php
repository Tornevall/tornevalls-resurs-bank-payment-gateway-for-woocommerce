<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection LongInheritanceChainInspection */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Converter\DiscountItemCollection;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Fee;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Product;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Shipping;
use WC_Abstract_Order;
use WC_Order_Item;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Tax;

use function array_merge;
use function is_array;

/**
 * Conversion of WC_Order or WC_Order_Refund to OrderLineCollection.
 */
class Order
{
    /**
     * @throws AttributeCombinationException
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public static function getOrderLines(
        WC_Abstract_Order $order
    ): OrderLineCollection {
        return new OrderLineCollection(
            data: array_merge(
                self::getProductLines(order: $order),
                self::getFeeLines(order: $order),
                self::getShippingLines(order: $order)
            )
        );
    }

    /**
     * Convert string (expected) to a float value with a precision of two. Also
     * ensure that we only return positive values, WC_Order_Refund will list
     * negative values, our API expects positives.
     *
     * @throws IllegalValueException
     */
    public static function convertFloat(
        mixed $value
    ): float {
        if (!is_numeric(value: $value)) {
            throw new IllegalValueException(
                message: 'Cannot convert none numeric value.'
            );
        }

        return round(
            num: abs(num: (float) $value),
            precision: 2
        );
    }

    /**
     * Resolve order item vat (tax) rate.
     *
     * NOTE: This is also utilised by methods which compile discount data.
     */
    public static function getVatRate(WC_Order_Item $item): float
    {
        $taxClass = (string) $item->get_tax_class();

        /* Passing get_tax_class() result without validation since anything it
           can possibly return should be acceptable to get_rates() */
        $rates = $item instanceof WC_Order_Item_Shipping ?
            WC_Tax::get_shipping_tax_rates(tax_class: $taxClass) :
            WC_Tax::get_rates(tax_class: $taxClass);

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
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws AttributeCombinationException
     */
    private static function getShippingLines(
        WC_Abstract_Order $order
    ): array {
        $result = [];

        $items = $order->get_items(types: 'shipping');

        if (!is_array(value: $items)) {
            throw new IllegalValueException(
                message: 'Failed to resolve shipping from order.'
            );
        }

        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!$item instanceof WC_Order_Item_Shipping) {
                continue;
            }

            $result[] = Shipping::toOrderLine(item: $item);
        }

        return $result;
    }

    /**
     * @throws AttributeCombinationException
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getProductLines(
        WC_Abstract_Order $order
    ): array {
        $result = [];

        $items = $order->get_items();

        if (!is_array(value: $items)) {
            throw new IllegalValueException(
                message: 'Failed to resolve items from order.'
            );
        }

        $discountCollection = new DiscountItemCollection(data: []);

        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            self::addDiscountData(item: $item, collection: $discountCollection);

            $result[] = Product::toOrderLine(product: $item);
        }

        return array_merge(
            $result,
            $discountCollection->getOrderLines()->toArray()
        );
    }

    /**
     * @throws AttributeCombinationException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function getFeeLines(
        WC_Abstract_Order $order
    ): array {
        $result = [];
        $items = $order->get_fees();

        if (!is_array(value: $items)) {
            throw new IllegalValueException(
                message: 'Failed to resolve fees from order.'
            );
        }

        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!$item instanceof WC_Order_Item_Fee) {
                continue;
            }

            $result[] = Fee::toOrderLine(fee: $item);
        }

        return $result;
    }

    /**
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private static function addDiscountData(
        WC_Order_Item_Product $item,
        DiscountItemCollection $collection
    ): void {
        // Total incl. tax before discounts are applied.
        $subtotal = Product::getSubtotal(product: $item);

        // Total incl. tax after discounts have been applied.
        $total = self::convertFloat(value: $item->get_total());

        // VAT amounts for subtotal and total.
        $subtotalVat = Product::getSubtotalVat(product: $item);
        $totalVat = self::convertFloat(value: $item->get_total_tax());

        // Similar checks are performed by WC to confirm discount.
        if ($subtotal <= $total) {
            return;
        }

        // Create new rate group / append amount to existing rate group.
        $collection->addRateData(
            rate: self::getVatRate(item: $item),
            amount: $subtotal + $subtotalVat - $total - $totalVat
        );
    }
}
